# Authentication architecture

Sources: `backend/src/Auth/AuthService.php`, `Authorization.php`, `backend/public/index.php`, `frontend/assets/app.js`, migration `0007_auth_sessions.sql`.

The login form POSTs username and password to `/api/v1/auth/login`. AuthService checks active users with password_verify; password_needs_rehash upgrades stored hashes with PASSWORD_DEFAULT. Ten failures for a username/IP pair in fifteen minutes block further attempts. Error responses currently distinguish unknown users from wrong passwords (account enumeration exposure).

Successful login generates 32 random bytes each for session and CSRF tokens. Only the SHA-256 session digest is persisted; the CSRF value is stored in user_sessions. The raw session is sent in the HttpOnly, SameSite=Lax serviceyar_session cookie. Secure depends on APP_SECURE_COOKIE=1 and must be configured with HTTPS. Default expiry is seven days, configured by SESSION_LIFETIME (minimum 300 seconds).

The browser loads `/api/v1/auth/me`, keeps CSRF in its in-memory session object, and sends X-CSRF-Token on writes. AuthService accepts the session cookie or a 64-hex Bearer token, hashes it, checks expiry/revocation and active user, and updates last_seen_at. Unsafe methods require constant-time CSRF comparison. This is an opaque database session, not a JWT or refresh-token flow.

Authorization separately checks roles, permissions and company access. Hiding a navigation entry is not the authorization boundary. Logout revokes the database session and expires the cookie. Password changes revoke previous sessions. No password or session token should appear in logs or source control.

## Samar comparison and remaining scope

See SAMAR-PARITY.md for implemented modules and observed evidence. Its later sections supersede earlier gaps: account statements, account defaults and cheque register already exist. Remaining documented gaps include detailed rotating shifts, automatic cheque journal integration, applying account defaults to all legacy financial writes, and the verified Mashhad transport authority export template. Live provider verification and hosted migration verification remain separate work. Financial equivalence requires approved tariff examples; an empty reference account cannot establish it.

This pass links login errors to fields and strengthens keyboard focus and touch targets, following UI UX Pro Max accessible-authentication guidance. Password-manager autocomplete and paste remain available. This is a focused authentication accessibility change, not a claim of complete product parity or a completed visual regression review.
