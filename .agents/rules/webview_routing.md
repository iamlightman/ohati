# Native Mobile WebView URL Routing Guard

- **Check Platform Protocol**: When implementing client-side SPA navigation (`navigateTo` / `pushState` / `replaceState`), always check whether the app is running in a native mobile WebView (`isNative` check for `capacitor:`, `file:`, or `Capacitor.isNativePlatform`).
- **Preserve Native Base Location**: In native WebView environments, NEVER append `.php` file extensions to `window.history.pushState` or `window.history.replaceState`. Use hash-based parameters (`#screenName`) or maintain `index.html` context.
- **Sync Mobile Build Assets**: Always mirror asset changes between web root and native build directories (`www/` or Capacitor `webDir`) so WebViews run identical JavaScript and DOM structures.
