#!/usr/bin/env bash
set -euo pipefail

SQLCMD=/opt/mssql-tools18/bin/sqlcmd

# msodbcsql18 (pulled in by mssql-tools18) is the ODBC driver pdo_sqlsrv needs.
# The repo package must match the runner's Ubuntu release; ubuntu-latest
# rolls between releases. Newer images preinstall packages-microsoft-prod
# with its keyring deleted, so the conffile flags reinstall it without a prompt.
. /etc/os-release
curl -sSL -O "https://packages.microsoft.com/config/ubuntu/${VERSION_ID}/packages-microsoft-prod.deb"
sudo dpkg -i --force-confnew --force-confmiss packages-microsoft-prod.deb
sudo apt-get update -qq
sudo ACCEPT_EULA=Y apt-get install -y mssql-tools18 unixodbc-dev

"$SQLCMD" -C -U sa -P Prado_Unitest1 -Q "CREATE DATABASE prado_unitest"
"$SQLCMD" -C -U sa -P Prado_Unitest1 -Q "CREATE LOGIN prado_unitest WITH PASSWORD='prado_unitest', CHECK_POLICY=OFF"
"$SQLCMD" -C -U sa -P Prado_Unitest1 -d prado_unitest \
  -Q "CREATE USER prado_unitest FOR LOGIN prado_unitest; ALTER ROLE db_owner ADD MEMBER prado_unitest;"
"$SQLCMD" -C -U sa -P Prado_Unitest1 -d prado_unitest \
  -i ./tests/unit/Data/SqlMap/scripts/sqlsrv/DataBase.sql
