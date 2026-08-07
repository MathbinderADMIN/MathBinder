# MathBinder Canvas LTI 1.3 Administrator Guide

MathBinder Core 30.27.0 is installation-ready for an authorized Canvas sandbox. Live transport is disabled after installation and remains fail-closed until every administrator gate passes.

## Registration URLs

Replace `https://mathbinder.com/wp/` if the WordPress base URL changes.

- Dynamic configuration JSON: `https://mathbinder.com/wp/wp-json/mathbinder/v1/canvas/config`
- OIDC login initiation: `https://mathbinder.com/wp/wp-json/mathbinder/v1/canvas/oidc/login`
- LTI launch and Deep Linking target: `https://mathbinder.com/wp/wp-json/mathbinder/v1/canvas/lti/launch`
- MathBinder JWKS: `https://mathbinder.com/wp/wp-json/mathbinder/v1/canvas/jwks`

## Required Canvas services

- LTI 1.3 OpenID Connect login and resource-link launch
- Deep Linking 2.0 for assignment selection
- Assignment and Grade Services: line items and scores
- Names and Role Provisioning Services: context membership read-only

## Authorization sequence

1. The Canvas root/account administrator approves MathBinder and creates an LTI 1.3 Developer Key using the configuration JSON.
2. Canvas supplies the client ID and deployment ID.
3. The MathBinder administrator enters the Canvas instance endpoints, client ID, deployment ID, MathBinder private key, and public JWK under **Settings → MathBinder Canvas**.
4. The MathBinder administrator validates the saved configuration. This local validation sends no data.
5. In an authorized sandbox only, the administrator explicitly enables the sandbox gate.
6. A test launch verifies the signed LTI message. The Canvas course and user remain pending review until an authorized MathBinder teacher or administrator confirms their mapping.
7. Roster changes are previewed before account creation or matching. Unmatched identities cannot receive assignments or grade passback.
8. A teacher publishes a Mastery Path through Deep Linking. Scores return only after teacher approval.

## Data ownership and safety

MathBinder remains the system of record for permanent accounts, classes, Mastery Paths, grades, notes, and Evidence Folders. Canvas identifiers are stored only as deployment-scoped external mappings. Disabling or removing a Canvas deployment does not delete MathBinder student work. Keys and tokens never appear on teacher screens or in exported diagnostic messages.

## Production gate

Core 30.27.0 permits sandbox authorization only. Production activation requires a separately reviewed deployment and must not be enabled by editing plugin files or bypassing the administrator gates.
