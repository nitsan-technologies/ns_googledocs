# Feature — Premium strip and ns_license free trial

*Status: Done. Requires ns_license 14.5.4 (deep-link support) to be released.*

## Licensing status

Freemium per the NITSAN Free/Freemium sheet. Premium product: https://t3planet.com/typo3-googledocs-extension
(not linked from the UI; the trial stays inside the TYPO3 backend).

## Dependency

`composer.json` `"nitsan/ns-license": "^14.5.4"`, `ext_emconf.php` `'ns_license' => '14.5.4-14.9.99'`.

## Strip

- Built by `Classes/Backend/PremiumStrip.php` (same pattern as `ns_revolution_slider`).
- Injected into every module view via `UserInfoController::initializeModuleTemplate()` and rendered in
  `Layouts/Default.html` directly below the navbar (`f:format.raw`).
- Content: `actions-rocket` core icon, headline and description on two lines, one `btn btn-primary`
  "Start Free Trial →" link (the arrow is `aria-hidden` markup, not part of the label; same tab).
- Labels in `locallang.xlf`:
  - `premium.strip.headline`: "You're using the free version of Google Docs"
  - `premium.strip.description`: "Unlock premium features and get more out of Google Docs."
  - `premium.strip.trial`: "Start Free Trial"
- Styling: `.ns-googledocs-premium-strip` (+ `-icon`, `-headline`, `-description`) in
  `Resources/Public/Css/backend-premium-strip.css` — light-blue card (`#e7f2fc`, border `#8eb6e8`, 4px `#2b6cb0`
  left accent, 6px radius, padding 14px 18px, text `#0d2c4a`), `light-dark()` for dark mode, button `#007bff`.
  Stylesheet is registered with `PageRenderer::addCssFile()` only when the strip HTML is non-empty.

## Trial link

`PremiumStrip::render()` builds the URL with the backend `UriBuilder`:
route `nitsan_nslicensemodule`, param `trial=ns_googledocs`, anchor `#free-section`.

ns_license's `get-license.js` (`maybeOpenTrialDeepLink()`) reads `?trial=ns_googledocs` and removes it from the
URL, then opens ns_license's trial modal for `ns_googledocs` (My Extensions free-section or Extensions catalog).

If the route is missing, `PremiumStrip` returns an empty string (no strip). With the declared dependency the
route is present; the ns_license module itself requires system maintainer access, while this module is open to
all backend users (`access: user`).
