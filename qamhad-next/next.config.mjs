/** @type {import('next').NextConfig} */
const nextConfig = {
  // Produce a fully static site in ./out — no Node.js runtime on the server.
  output: 'export',

  // Every route becomes a folder with its own index.html (e.g. /news/ →
  // news/index.html). This matches the existing clean-URL scheme and works
  // on plain Apache/Nginx shared hosting with no rewrites for listing pages.
  trailingSlash: true,

  // next/image optimization needs a server; static export must ship images
  // untouched. We keep the existing <img> tags and asset URLs verbatim.
  images: { unoptimized: true },

  // Dynamic detail routes (/news/{slug}, /video/{id}, /match/{slug}, ...) are
  // rendered client-side from the PHP API. A single shell is emitted per
  // dynamic segment; the real id/slug is read from the URL at runtime.
  // (See app/**/[param]/page.tsx + public/.htaccess deep-link rewrites.)
  reactStrictMode: true,
};

export default nextConfig;
