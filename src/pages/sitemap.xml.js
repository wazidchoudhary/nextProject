// pages/sitemap.xml.js
export const getServerSideProps = async ({ res }) => {
    const baseUrl = {
      development: 'http://localhost:3000',
      production: 'https://teflonbonehorncrafts.com',
    }[process.env.NODE_ENV];
  
    const staticPages = fs
      .readdirSync({
        development: 'pages',
        production: './',
      }[process.env.NODE_ENV])
      .filter((staticPage) => {
        return ![
          '_app.js',
          '_document.js',
          '_error.js',
          'sitemap.xml.js',
          // other pages to exclude
        ].includes(staticPage);
      })
      .map((staticPagePath) => {
        return `${baseUrl}/${staticPagePath}`;
      });
  
    // Add other dynamic routes if you have any
    const dynamicPages = []; // e.g., fetched from your database
  
    const allPages = [...staticPages, ...dynamicPages];
  
    const sitemap = `<?xml version="1.0" encoding="UTF-8"?>
    <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
      ${allPages
        .map((url) => {
          return `
            <url>
              <loc>${url}</loc>
            </url>
          `;
        })
        .join('')}
    </urlset>
    `;
  
    res.setHeader('Content-Type', 'text/xml');
    res.write(sitemap);
    res.end();
  
    return {
      props: {},
    };
  };
  
  const Sitemap = () => {};
  export default Sitemap;
  