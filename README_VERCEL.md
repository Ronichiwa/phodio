# Phodio — Vercel/Supabase version

This is the existing Phodio PHP application adapted only where necessary for Vercel + Supabase PostgreSQL.

- Existing UI/pages/business logic preserved.
- MySQL/MariaDB connection files removed.
- Supabase PostgreSQL connection is read from `DATABASE_URL`.
- PostgreSQL schema/data is in `supabase_schema.sql`.
- Vercel configuration is in `vercel.json`.
- Deployment instructions are in `VERCEL_SUPABASE_SETUP.md`.

See `VERCEL_SUPABASE_SETUP.md` before deploying.
