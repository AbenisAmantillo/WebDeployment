# Railway Deployment

## Firebase Admin Credentials

Railway does not have `config/firebase/service-account.json` by default, and Firebase service account JSON must not be committed to git.

Add this Railway Variable:

```text
FIREBASE_CREDENTIALS_JSON
```

Set its value to the full Firebase Admin SDK service account JSON downloaded from Firebase Console. Paste the complete JSON object, including the `project_id`, `client_email`, and `private_key` fields.

Keep the `private_key` value exactly as it appears in the downloaded JSON file. It should contain escaped newline sequences like `\n` inside the JSON string. Do not replace those `\n` sequences with literal line breaks inside the `private_key` value.

On container startup, the app writes `FIREBASE_CREDENTIALS_JSON` to:

```text
/tmp/firebase-service-account.json
```

It then exports:

```text
FIREBASE_CREDENTIALS=/tmp/firebase-service-account.json
```

The Kreait Firebase bundle uses `FIREBASE_CREDENTIALS`, so Railway will not try to open `%kernel.project_dir%/config/firebase/service-account.json`.

For local Docker development, keep using a local file path outside git:

```text
FIREBASE_CREDENTIALS=/var/www/html/config/firebase/service-account.json
```

Do not add `config/firebase/*.json` or `config/service-account.json` to git.

## Google Sign-In

Set these Railway Variables on the backend service:

```text
DEFAULT_URI=https://your-railway-app.up.railway.app
SYMFONY_TRUSTED_PROXIES=127.0.0.1,REMOTE_ADDR
OAUTH_GOOGLE_CLIENT_ID=your-google-oauth-client-id
OAUTH_GOOGLE_CLIENT_SECRET=your-google-oauth-client-secret
```

In Google Cloud Console, add this Authorized redirect URI to the same OAuth client:

```text
https://your-railway-app.up.railway.app/connect/google/check
```

If Google sign-in is called from a separate frontend origin, also set `CORS_ALLOW_ORIGIN` to include that frontend URL. For a single Railway-hosted Symfony site, CORS is not involved in the `/connect/google` browser redirect flow.
