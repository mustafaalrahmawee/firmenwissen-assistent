-- Eigene Datenbank für die Pest-Tests (läuft nur beim ersten Start mit leerem Volume)
CREATE DATABASE IF NOT EXISTS app_test;
GRANT ALL PRIVILEGES ON app_test.* TO 'app'@'%';
FLUSH PRIVILEGES;
