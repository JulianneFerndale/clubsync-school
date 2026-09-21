-- ClubSync — local PostgreSQL setup for on-premise deployment.
-- Run as: sudo -u postgres psql -f deploy/postgres-setup.sql
--
-- CHANGE THE PASSWORD BELOW before running this against a real server.

CREATE ROLE clubsync_app WITH LOGIN PASSWORD 'CHANGE_ME_BEFORE_RUNNING';

CREATE DATABASE clubsync OWNER clubsync_app;

\c clubsync

-- Least-privilege: the app role owns and can fully manage its own database,
-- but has no superuser/replication privileges.
GRANT ALL PRIVILEGES ON SCHEMA public TO clubsync_app;
ALTER DATABASE clubsync SET search_path TO public;
