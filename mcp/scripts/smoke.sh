#!/usr/bin/env bash
# Curl parity smoke test against the same Agent API the MCP client uses.
set -euo pipefail

: "${MSP_API_BASE:?Set MSP_API_BASE (e.g. http://localhost)}"
: "${MSP_API_TOKEN:?Set MSP_API_TOKEN (Sanctum PAT)}"

BASE="${MSP_API_BASE%/}/api/v1"
AUTH=(-H "Authorization: Bearer ${MSP_API_TOKEN}" -H "Accept: application/json")

echo "== GET ${BASE}/dashboard =="
curl -sS "${AUTH[@]}" "${BASE}/dashboard" | head -c 400
echo -e "\n"

echo "== GET ${BASE}/companies?per_page=3 =="
curl -sS "${AUTH[@]}" "${BASE}/companies?per_page=3" | head -c 400
echo -e "\n"

echo "== GET ${BASE}/contracts/upcoming-renewals?days=30&per_page=3 =="
curl -sS "${AUTH[@]}" "${BASE}/contracts/upcoming-renewals?days=30&per_page=3" | head -c 400
echo -e "\n"

echo "Smoke OK (HTTP reached; inspect JSON above for auth/RBAC issues)."
