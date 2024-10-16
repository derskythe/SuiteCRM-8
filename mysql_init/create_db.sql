--CREATE USER app@'%' identified by '!ChangeMe!';
CREATE DATABASE app;
GRANT ALL PRIVILEGES ON app.* TO app_user@'%';
