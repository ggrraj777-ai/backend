#!/bin/bash

# Get Cloud Run error logs for the backend service

echo "Fetching error logs from Cloud Run..."
echo "Service: backend"
echo "Region: europe-west1"
echo "Project: gauva-15d9a"
echo ""

# Get stderr logs (where Laravel errors appear)
gcloud logging read "resource.type=cloud_run_revision AND resource.labels.service_name=backend AND severity>=ERROR" \
  --limit 50 \
  --format json \
  --project gauva-15d9a \
  | jq -r '.[] | "\(.timestamp) - \(.textPayload // .jsonPayload.message)"'

echo ""
echo "======================================"
echo "Looking for Laravel specific errors..."
echo "======================================"

# Get all recent logs including warnings
gcloud run services logs read backend \
  --region europe-west1 \
  --project gauva-15d9a \
  --limit 100 \
  | grep -E "error|Error|ERROR|Exception|SQLSTATE|APP_KEY|Failed|failed"

echo ""
echo "Done! Look for error messages above."

