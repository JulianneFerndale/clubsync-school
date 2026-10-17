# Google Sign-In — Setup Checklist

ClubSync's login and registration pages offer **Continue with Google** alongside email and password. Only accounts of the **`sccpag.edu.ph` Google Workspace** can use it. ClubSync's server decides this from Google's verified ID token, using the hosted-domain `hd` claim and a verified address, and it decides **before Firebase is contacted**. A personal Gmail account, another school's account, or a personal account opened with an SCC-looking address is refused. No Firebase account is ever made for one. The rules are in `workflows/POLICY.md` §Authentication.

The button stays hidden until `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` are set.

| Who | What happens |
|---|---|
| Registered with a password, first Google sign-in | Google → "Confirm it's you": they enter their ClubSync password **once**, and Google is linked to their account |
| Registered with a password, after that | Google → signed straight in (Remember Me honoured) |
| SCC Google account, not registered | Google → the **Register with Google** form. Name and email come from Google, and **no password** is set. They enter only their EDP number, year level and mobile number, then department, course and proof of enrollment |
| Registered with Google | Google → signed straight in. The account has no password: a password sign-in is told to use Google, and the profile has no password to change |
| Anyone else | Refused on the login page: "Only Saint Columban College Google accounts (@sccpag.edu.ph)…" |

---

## Part A — Firebase project (the ClubSync maintainer)

### A1. Turn on the Google provider
- [ ] <https://console.firebase.google.com> → the ClubSync project → **Authentication → Sign-in method → Add new provider → Google → Enable**. Set a support email and save.
- [ ] Open the Google provider again and expand **Web SDK configuration**. Copy the **Web client ID** and **Web client secret**. These become `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET`.
  - Firebase only accepts Google tokens issued to this client, or to clients listed under **Safelist client IDs from external projects**. If you create a separate OAuth client instead, add its ID to that safelist.

### A2. Keep one account per email
- [ ] **Authentication → Settings → User account linking** must be **Link accounts that use the same email** (the default). With "Create multiple accounts", a Google sign-in creates a second Firebase user, and ClubSync refuses it.

### A3. Authorized domains
- [ ] **Authentication → Settings → Authorized domains** includes the production host. `localhost` is there by default.

---

## Part B — Google Cloud console (same project as Firebase)

### B1. Redirect URIs
- [ ] <https://console.cloud.google.com> → the Firebase project → **APIs & Services → Credentials** → **Web client (auto created by Google Service)**.
- [ ] Under **Authorized redirect URIs** add, exactly:
  - `https://clubsyncing.test/auth/google/callback` (local Herd site, see below)
  - `https://<production host>/auth/google/callback`
  - Google refuses a plain `http://` redirect for any host except `localhost`. Checked on 2026-10-07: `http://clubsyncing.test/...` got "doesn't comply with Google's OAuth 2.0 policy". So locally, run `herd secure clubsyncing` once and set `APP_URL=https://clubsyncing.test`.
- [ ] Save. Changes can take a few minutes.

### B2. OAuth consent screen
- [ ] **APIs & Services → OAuth consent screen**. App name *ClubSync — Saint Columban College*, support email, and the authorised domain of the production host.
- [ ] Scopes: only `openid`, `email` and `profile`. These are non-sensitive, so Google does not need to verify the app.
- [ ] **User type:**
  - If the project belongs to the SCC Google Workspace organization, choose **Internal**. Google itself then refuses non-SCC accounts, on top of ClubSync's own check.
  - Otherwise choose **External** and **Publish app** (status *In production*). While it is *Testing*, only the listed test users can sign in.

---

## Part C — ClubSync server

- [ ] In `.env` (never commit it):
  ```
  GOOGLE_CLIENT_ID=<Web client ID>
  GOOGLE_CLIENT_SECRET=<Web client secret>
  # Only if the public URL differs from the host Laravel sees (e.g. a TLS proxy):
  GOOGLE_REDIRECT_URI=https://<production host>/auth/google/callback
  ```
- [ ] `php artisan config:clear` (or `config:cache` on the server).
- [ ] Open `/login`: **Continue with Google** now appears under the Login button.

## Check it

1. With an `@sccpag.edu.ph` account that is already registered: Continue with Google → confirm the password once → dashboard. Sign out and repeat: straight in, with no password asked.
2. With a personal Gmail account: refused with the SCC-only message. Firebase console → Users shows **no** new user.
3. With an unregistered `@sccpag.edu.ph` account: you land on **Register with Google**, which asks only for EDP number, year level and mobile number. Finish department, course and proof of enrollment, sign out, then Continue with Google again: you are straight in.

## Troubleshooting

| Seen | Cause |
|---|---|
| Google says `redirect_uri_mismatch` | B1: the callback URL is not listed exactly (scheme, host, path), or `GOOGLE_REDIRECT_URI` is needed |
| "Google sign-in is unavailable right now" + log `Firebase refused a Google sign-in` `OPERATION_NOT_ALLOWED` | A1: the Google provider is not enabled |
| Same message, log code `INVALID_IDP_RESPONSE` | A1: the client ID is not Firebase's Web client and is not safelisted |
| "Google sign-in could not be completed" + log `the code exchange failed` `invalid_client` | wrong client secret |
| "This Google account cannot sign in to ClubSync yet" | A2 is set to multiple accounts per email, or the user's `firebase_uid` points to a different Firebase user. Check the log entry for both uids |
