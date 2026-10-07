# Phodio — Vercel + Supabase setup

## 1. Create the Supabase database

1. Create/open your Supabase project.
2. Open **SQL Editor**.
3. Run `supabase_schema.sql` completely.

## 2. Get the database connection string

In Supabase, open **Connect** and choose **Session pooler**. Copy the PostgreSQL URI.
Use the Session pooler URI (port 5432) for this PHP application because it uses PDO prepared statements and transactions.

## 3. Add the Vercel environment variable

In Vercel: Project -> Settings -> Environment Variables, add:

`DATABASE_URL` = your Supabase Session pooler PostgreSQL connection string.

Do not put the real password in this ZIP or commit it to Git.

## 4. Deploy

Import the project into Vercel. The included `vercel.json` configures the community PHP runtime and routes the existing PHP URLs through `/api`.

## Important limitation: profile uploads

The original application writes uploaded profile images to `uploads/profile/`. Vercel functions use an ephemeral filesystem, so those uploaded files are not permanent. The existing application logic was intentionally left otherwise unchanged. If persistent profile images are required later, move only that upload feature to Supabase Storage.

## Authentication/session note

The existing application uses PHP sessions. The Vercel PHP runtime can execute sessions, but serverless instances are not a persistent PHP server. For a small school/demo deployment this may be acceptable; for production authentication, migrate session/auth handling to a persistent/session-backed mechanism.
