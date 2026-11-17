import type { NextConfig } from 'next';
const config: NextConfig = { poweredByHeader: false, allowedDevOrigins: ['localhost','127.0.0.1'], outputFileTracingRoot: process.cwd() };
export default config;
