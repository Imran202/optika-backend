@component('mail::message')
# Detalji nove narudžbe

@if(isset($orderData['products']))
    {{-- Cart order with multiple products --}}
    @foreach($orderData['products'] as $product)
    **Proizvod:** {{ $product['productName'] ?? '' }}
    **Količina:** {{ $product['quantity'] ?? '' }}
    **Cijena po komadu:** {{ $product['pricePerItem'] ?? '' }} KM
    @if(!$loop->last)
    ---
    @endif
    @endforeach
@else
    {{-- Single product order --}}
    **Proizvod:** {{ $orderData['productName'] ?? '' }}
    **Količina:** {{ $orderData['quantity'] ?? '' }}
    **Cijena po komadu:** {{ $orderData['pricePerItem'] ?? '' }} KM
@endif

**Ukupna cijena:** {{ $orderData['totalPrice'] ?? '' }} KM

---

## Informacije o kupcu

**Ime i prezime:** {{ $orderData['customerInfo']['name'] ?? '' }}

**Email:** {{ $orderData['customerInfo']['email'] ?? '' }}

**Telefon:** {{ $orderData['customerInfo']['phone'] ?? '' }}

**Adresa:** {{ $orderData['customerInfo']['address'] ?? '' }}

**Grad:** {{ $orderData['customerInfo']['city'] ?? '' }}

@if(!empty($orderData['customerInfo']['note']))
**Dodatne napomene:** {{ $orderData['customerInfo']['note'] }}
@endif

**Loyalty bodovi:** {{ $orderData['loyaltyPoints'] ?? '' }}

@endcomponent
