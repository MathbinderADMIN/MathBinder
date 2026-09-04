# MathBinder Canvas LTI 1.3 Administrator Guide

MathBinder Core 30.51.5 continues the authenticated Canvas development phase. It is ready for registration, embedded mapped-user launch authentication, instructor lesson selection, front-end student assignment submission, and launch testing in an authorized Canvas Open Source or hosted sandbox. Live production transport remains fail-closed until every administrator gate passes.

## Registration URLs

Replace `https://mathbinder.com/` if the WordPress base URL changes.

- Dynamic configuration JSON: `https://mathbinder.com/wp-json/mathbinder/v1/canvas/config`
- OIDC login initiation: `https://mathbinder.com/wp-json/mathbinder/v1/canvas/oidc/login`
- LTI launch and Deep Linking target: `https://mathbinder.com/wp-json/mathbinder/v1/canvas/lti/launch`
- MathBinder JWKS: `https://mathbinder.com/wp-json/mathbinder/v1/canvas/jwks`

## Required Canvas services

- LTI 1.3 OpenID Connect login and resource-link launch
- Deep Linking 2.0 for assignment selection
- Assignment and Grade Services: line items and scores
- Names and Role Provisioning Services: context membership read-only

## Authorization sequence

1. The Canvas root/account administrator approves MathBinder and creates an LTI 1.3 Developer Key using the configuration JSON.
2. Canvas supplies the client ID and deployment ID.
3. The MathBinder administrator opens **Settings → MathBinder Canvas** and generates the MathBinder RS256 signing key. The private key remains sealed in WordPress; Canvas receives only the public JWKS URL.
4. The administrator enters the Canvas base URL, platform issuer, client ID, deployment ID, JWKS URL, authorization URL, and access-token URL.
5. The MathBinder administrator validates the saved configuration. This local validation sends no data and confirms that the private key matches the public JWK.
6. In an authorized sandbox only, the administrator explicitly enables the sandbox gate.
7. A test launch verifies the signed LTI message. The Canvas course and user remain pending review until an authorized MathBinder administrator confirms their mapping.
8. Roster changes are previewed before account creation or matching. Unmatched identities cannot receive assignments or grade passback.
9. A teacher selects a published Binder Page through Deep Linking. Canvas stores a signed LTI resource link that re-verifies the course and identity before opening the selected lesson.

## Hosted Canvas endpoint defaults

- Platform issuer: `https://canvas.instructure.com`
- Production authorization endpoint: `https://sso.canvaslms.com/api/lti/authorize_redirect`
- Production JWKS endpoint: `https://sso.canvaslms.com/api/lti/security/jwks`
- Access-token endpoint: `https://canvas.instructure.com/login/oauth2/token`

Canvas Open Source installations may use deployment-specific endpoints. Enter the values published by that installation rather than assuming the hosted defaults.

## Data ownership and safety

MathBinder remains the system of record for permanent accounts, classes, Mastery Paths, grades, notes, and Evidence Folders. Canvas identifiers are stored only as deployment-scoped external mappings. Disabling or removing a Canvas deployment does not delete MathBinder student work. Keys and tokens never appear on teacher screens or in exported diagnostic messages.

## Production gate

Core 30.51.5 provides Disabled and Sandbox operating modes. Live mode is visibly locked. Production activation requires a separately reviewed deployment and must not be enabled by editing plugin files or bypassing the administrator gates.

## Core 30.51.5 test console

Under **Settings → MathBinder Canvas**, administrators can run local readiness diagnostics, simulate a launch claim, preview roster matches from test JSON, preview Deep Linking, grade-passback policy, and Evidence Folder handoff behavior, review deployment-scoped mappings, and inspect sanitized synchronization history. These previews do not contact Canvas or mutate MathBinder records.
