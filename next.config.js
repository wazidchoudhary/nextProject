/** @type {import('next').NextConfig} */
const nextConfig = {
  reactStrictMode: false,
  images: {
    formats: ['image/avif', 'image/webp'],
    domains: ['firebasestorage.googleapis.com', 'suboorkhan.com', 'res.cloudinary.com'],
  },
};

module.exports = nextConfig;
