export default function handler(req, res) {
    const sitemapIndex = `<?xml version="1.0" encoding="UTF-8"?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<sitemap><loc>https://www.vikingcraftstore.com/sitemap-0.xml</loc></sitemap>
<sitemap><loc>https://www.vikingcraftstore.com/server-sitemap.xml</loc></sitemap>
</sitemapindex>`;

    res.setHeader('Content-Type', 'application/xml');
    res.setHeader('Cache-Control', 'public, s-maxage=86400, stale-while-revalidate');
    res.status(200).send(sitemapIndex);
}
