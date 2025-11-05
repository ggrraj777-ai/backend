# Assets & Build

## Tooling

- Laravel Mix (`laravel-mix`) configured in `webpack.mix.js`.
- Entry points:
  - JS: `resources/js/app.js` → `public/js/app.js`
  - CSS: `resources/css/app.css` → `public/css/app.css`

## Scripts (`package.json`)

- `npm run dev` → `mix`
- `npm run watch` → `mix watch`
- `npm run hot` → HMR
- `npm run prod` → `mix --production`

## Public Assets

- `public/assets/`, `public/landing-page/`, `public/js/`, `public/css/`, `favicon.ico`.
- Build manifest: `public/mix-manifest.json`.
