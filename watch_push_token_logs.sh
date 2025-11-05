#!/bin/bash

echo "======================================"
echo "  PUSH TOKEN LOGS - LIVE MONITORING"
echo "======================================"
echo ""
echo "Monitoring: storage/logs/laravel.log"
echo "Filter: Push token related logs"
echo "Press Ctrl+C to stop"
echo ""
echo "--------------------------------------"

tail -f storage/logs/laravel.log | grep -i --line-buffered "updatepushtoken\|push token\|🔔\|📝\|✅.*push\|❌.*push"

