import Head from 'next/head';
import { useRouter } from 'next/router';
import React from 'react';

const CommonMeta = ({ title = 'Create Next App', description, image = '/assets/', siteName = 'teflon bone horn crafts', keywords = '' }) => {
    const router = useRouter();
    const url = router.asPath;
    const keywordsMeta = ['bone horn crafts', 'teflon bone horn crafts', 'horn crafts', description, title, siteName, 'as international', keywords, 'cheap bone horn crafts', 'cheap horn craft items', 'cheap bone horn craft items'];
    const k = [...keywordsMeta, ...keywordsMeta.map(k => k.toUpperCase())].join(', ')
    return (
        <Head>
            <title>{title}</title>
            <meta name="description" content={description} />
            <meta name="viewport" content="width=device-width, initial-scale=1" />
            <link rel="icon" href="/favicon.ico" />
            <meta name="description" content={description} />
            <meta name="keywords" content={k}/>
            {/* Open Graph Meta Tags */}
            <meta property="og:title" content={title} />
            <meta property="og:description" content={description} />
            <meta property="og:image" content={image} />
            <meta property="og:url" content={url} />
            <meta property="og:site_name" content={siteName} />
            <meta property="og:type" content="website" />

            {/* Twitter Meta Tags */}
            <meta name="twitter:title" content={title} />
            <meta name="twitter:description" content={description} />
            <meta name="twitter:image" content={image} />
            <meta name="twitter:card" content="summary_large_image" />
        </Head>
    );
};

export default CommonMeta;
