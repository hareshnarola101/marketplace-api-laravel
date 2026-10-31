-- Separate database so running the test suite against MySQL never touches demo data.
CREATE DATABASE IF NOT EXISTS testing;
GRANT ALL PRIVILEGES ON testing.* TO 'marketplace'@'%';
