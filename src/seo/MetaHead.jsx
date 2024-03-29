import Head from 'next/head';
import { useRouter } from 'next/router';
import React from 'react';
const host = 'https://www.teflonbonehorncraft.com';
const CommonMeta = ({ title = 'Create Next App', description, image = '/assets/', siteName = 'teflon bone horn crafts', keywords = '' }) => {
    const router = useRouter();
    const url = host + router.asPath;
    const keywordsMeta = [
        'bone horn crafts',
        'teflon bone horn crafts',
        'horn crafts',
        description,
        title,
        siteName,
        'as international',
        keywords,
        'Bone Horn Crafts',
        'Teflon Bone Horn Crafts',
        'Horn Crafts',
        'Bull Shoe Horn Supplier',
        'Dyed Stabilized Bone Scales Exporter',
        'Water Buffalo Horn Scale Manufacturer',
        'Buffalo Horn Plate Exporter in India',
        'Wooden Knife Handle Manufacturer',
        'Bone Bridge Pin Blank',
        'Wooden Comb Manufacturer in India',
        'Guitar Horn Saddle Supplier',
        'Bone Pen Blank Exporter',
        'Bone Hair Pipe Bead Exporter in India',
        'Drinking Horn and Mug Supplier',
        'Wooden Cutlery Manufacturer',
        'Bone Inlay Jewellery Box Supplier',
        'Bull Horn Cutlery Supplier',
        'Buffalo Horn Spacer Manufacturer',
        'Cheap Bone Horn Crafts',
        'Cheap Horn Craft Items',
        'AS International',
    ];
    const k = [...keywordsMeta, ...keywordsMeta.map((k) => k.toUpperCase())].join(', ');
    return (
        <Head>
            <title>{title}</title>
            <meta name="description" content={description} />
            <meta name="viewport" content="width=device-width, initial-scale=1" />
            <link title="icon" rel="icon" href="/favicon.ico" />
            <meta name="description" content={description} />
            <meta name="robots" content="index, follow"></meta>
            <meta name="author" content="AS INTERNATIONAL"></meta>
            <meta name="keywords" content={k} />
            {/* Open Graph Meta Tags */}
            <meta property="og:title" content={title} />
            <meta property="og:description" content={description} />
            <meta property="og:image" content={'/assets/image/logo.webp'} />
            <meta property="og:url" content={url} />
            <meta property="og:site_name" content={siteName} />
            <meta property="og:type" content="website" />
            <meta property="fb:app_id" content="https://www.facebook.com/A.SINTERNATIONAL252?mibextid=ZbWKwL" />

            {/* Twitter Meta Tags */}
            <meta name="twitter:title" content={title} />
            <meta name="twitter:description" content={description} />
            <meta name="twitter:image" content={'/assets/image/logo.webp'} />
            <meta name="twitter:site" content="@ChodharyTanish" />
            <meta name="twitter:url" content="https://twitter.com/ChodharyTanish" />
            <meta name="twitter:card" content="summary_large_image" />
            <link rel="canonical" href={url} />
        </Head>
    );
};

export default CommonMeta;
