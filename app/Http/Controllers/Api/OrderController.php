<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    private $woocommerce_url = 'https://optika-salihbegovic.com/wp-json/wc/v3';
    private $consumer_key = 'ck_54e6bb695a2b51391e1cc37a49c86ce182d635c3';
    private $consumer_secret = 'cs_14de5c93d1242014b7844ef1dafd02b4f1ac8c3c';

    public function sendOrder(Request $request)
    {
        $orderData = $request->all();
        
        // Validate required fields for both single product and cart orders
        if (!isset($orderData['customerInfo'])) {
            return response()->json(['message' => 'Customer information is required.'], 400);
        }
        
        // Check if it's a single product order (has productName) or cart order (has products array)
        if (!isset($orderData['productName']) && !isset($orderData['products'])) {
            return response()->json(['message' => 'Product information is required.'], 400);
        }

        try {
            $user = $request->user();
            $pointsUsed = floatval($orderData['pointsUsed'] ?? 0);
            $orderTotal = floatval($orderData['orderTotal'] ?? 0);
            
            // Validacija: proveri da li korisnik ima dovoljno points-a
            // Points se u bazi čuvaju kao points * 10
            if ($pointsUsed > 0 && $user) {
                $userPointsInKM = $user->points / 10;
                if ($userPointsInKM < $pointsUsed) {
                    return response()->json([
                        'message' => 'Nemate dovoljno na računu. Imate ' . number_format($userPointsInKM, 2) . ' KM, a pokušavate iskoristiti ' . number_format($pointsUsed, 2) . ' KM.',
                        'available_points' => $userPointsInKM,
                        'requested_points' => $pointsUsed
                    ], 400);
                }
            }
            
            // Nakon uspješne narudžbe, procesuj points i cashback PRVO
            if ($user) {
                $this->processPointsAndCashback($user, $pointsUsed, $orderTotal);
                // Refresh korisnika da dobijemo ažurirano stanje
                $user->refresh();
            }
            
            // Kreiraj WooCommerce narudžbu (nakon što su points procesirani)
            $wooOrder = $this->createWooCommerceOrder($orderData);
            
            if (!$wooOrder['success']) {
                return response()->json(['message' => 'Greška prilikom kreiranja narudžbe u WooCommerce.', 'error' => $wooOrder['error']], 500);
            }

            return response()->json([
                'message' => 'Narudžba uspješno kreirana.',
                'order_id' => $wooOrder['order_id'],
                'points_used' => $pointsUsed,
                'cashback_earned' => $this->calculateCashback($orderTotal),
                'new_balance' => $user ? ($user->fresh()->points / 10) : 0
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Greška prilikom obrade narudžbe.', 
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Procesuj oduzimanje points-a i dodavanje cashback-a
     */
    private function processPointsAndCashback($user, $pointsUsed, $orderTotal)
    {
        // Points se u bazi čuvaju kao points * 10, tako da trebamo konvertovati
        // Oduzmi iskorištene points i kreiraj transakciju
        if ($pointsUsed > 0) {
            $pointsUsedForDB = $pointsUsed * 10;
            $user->points -= $pointsUsedForDB;
            
            // Kreiraj transakciju za oduzimanje
            $this->createTransaction(
                $user,
                $pointsUsed,
                'skinuto',
                'Online Shop - Plaćanje'
            );
        }
        
        // Dodaj cashback i kreiraj transakciju
        $cashbackPercentage = config('discount.cashback_percentage', 5);
        $cashbackAmount = ($orderTotal * $cashbackPercentage) / 100;
        
        if ($cashbackAmount > 0) {
            $cashbackForDB = $cashbackAmount * 10;
            $user->points += $cashbackForDB;
            
            // Kreiraj transakciju za cashback
            $this->createTransaction(
                $user,
                $cashbackAmount,
                'dodato',
                'Online Shop - Cashback ' . $cashbackPercentage . '%'
            );
        }
        
        $user->save();
        
        \Log::info('Points processed for order', [
            'user_id' => $user->id,
            'points_used' => $pointsUsed,
            'cashback_earned' => $cashbackAmount,
            'new_balance' => $user->points / 10
        ]);
    }

    /**
     * Kreiraj transakciju u bazi
     */
    private function createTransaction($user, $amount, $action, $vrsta)
    {
        try {
            // Points se čuvaju kao points * 10 u bazi
            $pointsForDB = $amount * 10;
            
            // Insert transaction - transcation_id je auto-increment (automatski se generiše)
            $transactionId = DB::table('transactions')->insertGetId([
                'poslovnica' => 'Loyalty App',
                'rfid' => $user->rfid,
                'user' => $user->username,
                'date' => now(),
                'points' => $pointsForDB,
                'action' => $action, // 'dodato' ili 'skinuto'
                'vrsta' => $vrsta,
            ], 'transcation_id');
            
            \Log::info('Transaction created', [
                'transaction_id' => $transactionId,
                'user' => $user->username,
                'rfid' => $user->rfid,
                'amount' => $amount,
                'action' => $action,
                'type' => $vrsta
            ]);
            
            return $transactionId;
        } catch (\Exception $e) {
            \Log::error('Failed to create transaction', [
                'error' => $e->getMessage(),
                'user' => $user->username
            ]);
            return null;
        }
    }

    /**
     * Izračunaj cashback za dati iznos
     */
    private function calculateCashback($amount)
    {
        $cashbackPercentage = config('discount.cashback_percentage', 5);
        return ($amount * $cashbackPercentage) / 100;
    }



    private function createWooCommerceOrder($orderData)
    {
        try {
            $customerInfo = $orderData['customerInfo'];
            
            // Pripremi line items za WooCommerce sa finalnom cijenom
            $lineItems = [];
            $pointsUsed = floatval($orderData['pointsUsed'] ?? 0);
            $finalPriceAfterPoints = floatval($orderData['finalPriceAfterPoints'] ?? 0);
            
            if (isset($orderData['products'])) {
                // Multiple products from cart
                // Izračunaj proporciju za svaki proizvod
                $totalWithDiscount = floatval($orderData['totalPrice'] ?? 0);
                foreach ($orderData['products'] as $product) {
                    $productTotal = floatval($product['pricePerItem']) * (int)$product['quantity'];
                    $productProportion = $totalWithDiscount > 0 ? ($productTotal / $totalWithDiscount) : 0;
                    $productFinalPrice = $productProportion * $finalPriceAfterPoints;
                    
                    $lineItems[] = [
                        'product_id' => (int)$product['productId'],
                        'quantity' => (int)$product['quantity'],
                        'subtotal' => number_format($productFinalPrice, 2, '.', ''),
                        'total' => number_format($productFinalPrice, 2, '.', '')
                    ];
                }
            } else {
                // Single product
                $quantity = (int)($orderData['quantity'] ?? 1);
                
                $lineItems[] = [
                    'product_id' => (int)$orderData['productId'],
                    'quantity' => $quantity,
                    'subtotal' => number_format($finalPriceAfterPoints, 2, '.', ''),
                    'total' => number_format($finalPriceAfterPoints, 2, '.', '')
                ];
            }
            
            // Pripremi podatke za WooCommerce narudžbu
            $wooOrderData = [
                'payment_method' => 'cod',
                'payment_method_title' => 'Loyalty App',
                'set_paid' => false,
                'billing' => [
                    'first_name' => $this->extractFirstName($customerInfo['name']),
                    'last_name' => $this->extractLastName($customerInfo['name']),
                    'address_1' => $customerInfo['address'],
                    'city' => $customerInfo['city'],
                    'postcode' => '-',
                    'country' => 'BA',
                    'email' => $customerInfo['email'],
                    'phone' => $customerInfo['phone']
                ],
                'shipping' => [
                    'first_name' => $this->extractFirstName($customerInfo['name']),
                    'last_name' => $this->extractLastName($customerInfo['name']),
                    'address_1' => $customerInfo['address'],
                    'city' => $customerInfo['city'],
                    'postcode' => '-',
                    'country' => 'BA'
                ],
                'line_items' => $lineItems,
                'shipping_lines' => [
                    [
                        'method_id' => 'flat_rate',
                        'method_title' => 'Dostava',
                        'total' => '0.00'
                    ]
                ],
                'fee_lines' => [],
                'coupon_lines' => [],
                'meta_data' => [],
                'customer_note' => $this->formatCustomerNote($orderData, $customerInfo),
                'status' => 'processing'
            ];

            // Pošalji zahtjev za kreiranje narudžbe
            $response = Http::withBasicAuth($this->consumer_key, $this->consumer_secret)
                ->post($this->woocommerce_url . '/orders', $wooOrderData);

            if ($response->successful()) {
                $order = $response->json();
                return [
                    'success' => true,
                    'order_id' => $order['id']
                ];
            } else {
                // Log error details for debugging
                \Log::error('WooCommerce API Error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'request_data' => $wooOrderData
                ]);
                
                return [
                    'success' => false,
                    'error' => 'WooCommerce API Error: ' . $response->status() . ' - ' . $response->body()
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function extractFirstName($fullName)
    {
        $parts = explode(' ', trim($fullName));
        return $parts[0] ?? '';
    }

    private function extractLastName($fullName)
    {
        $parts = explode(' ', trim($fullName));
        return count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '';
    }

    /**
     * Formatira customer note sa svim loyalty informacijama
     */
    private function formatCustomerNote($orderData, $customerInfo)
    {
        $user = request()->user();
        $note = "═══════════════════════════════════════\n";
        $note .= "        LOYALTY APP NARUDŽBA\n";
        $note .= "═══════════════════════════════════════\n\n";
        
        // RFID Kartica
        if ($user && !empty($user->rfid)) {
            $note .= "🎫 BROJ KARTICE (RFID): " . $user->rfid . "\n";
            $note .= "───────────────────────────────────────\n\n";
        }
        
        // Detalji proizvoda i cijena
        $note .= "📦 DETALJI NARUDŽBE:\n";
        
        if (isset($orderData['products'])) {
            // Multiple products
            foreach ($orderData['products'] as $product) {
                $originalPrice = floatval($product['originalPrice'] ?? 0);
                $discountAmount = floatval($product['discountAmount'] ?? 0);
                $discountPercentage = floatval($product['discountPercentage'] ?? 0);
                $quantity = (int)($product['quantity'] ?? 1);
                $totalOriginal = $originalPrice * $quantity;
                
                $note .= "\n   Proizvod: " . ($product['productName'] ?? '') . "\n";
                $note .= "   Količina: {$quantity}\n";
                $note .= "   Originalna cijena: " . number_format($totalOriginal, 2) . " KM\n";
                $note .= "   Popust ({$discountPercentage}%): -" . number_format($discountAmount, 2) . " KM\n";
            }
        } else {
            // Single product
            $originalPrice = floatval($orderData['originalPrice'] ?? 0);
            $discountAmount = floatval($orderData['discountAmount'] ?? 0);
            $discountPercentage = floatval($orderData['discountPercentage'] ?? 0);
            $quantity = (int)($orderData['quantity'] ?? 1);
            $totalOriginal = $originalPrice * $quantity;
            
            $note .= "\n   Proizvod: " . ($orderData['productName'] ?? '') . "\n";
            $note .= "   Količina: {$quantity}\n";
            $note .= "   Originalna cijena: " . number_format($totalOriginal, 2) . " KM\n";
            $note .= "   Popust ({$discountPercentage}%): -" . number_format($discountAmount, 2) . " KM\n";
        }
        
        // Points korišteni za plaćanje
        $pointsUsed = floatval($orderData['pointsUsed'] ?? 0);
        $totalWithDiscount = floatval($orderData['totalPrice'] ?? 0);
        $finalPriceAfterPoints = floatval($orderData['finalPriceAfterPoints'] ?? 0);
        
        $note .= "\n   Cijena sa popustom: " . number_format($totalWithDiscount, 2) . " KM\n";
        
        if ($pointsUsed > 0) {
            $note .= "   Iskorišteno sa računa: -" . number_format($pointsUsed, 2) . " KM\n";
        }
        
        $note .= "   ───────────────────────────────\n";
        $note .= "   FINALNA CIJENA ZA PLAĆANJE: " . number_format($finalPriceAfterPoints, 2) . " KM\n";
        
        // Loyalty Info
        $loyaltyDiscount = config('discount.loyalty_discount_percentage', 10);
        $note .= "\n💎 LOYALTY PROGRAM INFO:\n";
        $note .= "   • Akcija: {$loyaltyDiscount}% popusta za loyalty članove\n";
        $note .= "   • Stanje prije kupovine: " . number_format($orderData['loyaltyPoints'] ?? 0, 2) . " KM\n";
        
        // Cashback info
        $cashbackPercentage = config('discount.cashback_percentage', 5);
        $cashbackAmount = $this->calculateCashback($orderData['orderTotal'] ?? 0);
        $note .= "\n🎉 CASHBACK NAGRADA:\n";
        $note .= "   • Povrat: " . number_format($cashbackAmount, 2) . " KM ({$cashbackPercentage}%)\n";
        
        // Novo stanje (korisnik je već ažuriran u processPointsAndCashback)
        if ($user) {
            // Refresh korisnika da dobijemo najnovije stanje iz baze
            $user->refresh();
            // Points se u bazi čuvaju kao points * 10, tako da trebamo dijeliti sa 10
            $note .= "   • Novo stanje: " . number_format($user->points / 10, 2) . " KM\n";
        }
        
        $note .= "\n═══════════════════════════════════════\n\n";
        
        // Dodatna napomena od korisnika
        if (!empty($customerInfo['note'])) {
            $note .= "📝 NAPOMENA KUPCA:\n";
            $note .= $customerInfo['note'] . "\n\n";
            $note .= "═══════════════════════════════════════\n";
        }
        
        return $note;
    }
}
