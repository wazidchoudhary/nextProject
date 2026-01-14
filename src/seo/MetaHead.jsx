import Head from 'next/head';
import { useRouter } from 'next/router';
import React from 'react';
const host = 'https://www.vikingcraftstore.com';
const CommonMeta = ({ title = 'Viking Craft Store', description, image = '/assets/image/logo.webp', siteName = 'Viking Craft Store', keywords = '' }) => {
    const router = useRouter();
    const url = host + router.asPath;
    const keywordsMeta = [
        'viking craft store',
        'handmade crafts',
        'bone horn crafts',
        'knife handles',
        'custom knife scales',
        description,
        title,
        siteName,
        'as international',
        keywords,
        'Viking Knife Handles',
        'Premium Knife Scales',
        'Bone Knife Handle Scales',
        'Horn Knife Handle Scales',
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
        'Viking Drinking Horn',
        'Medieval Drinking Horn',
        'Wooden Cutlery Manufacturer',
        'Bone Inlay Jewellery Box Supplier',
        'Bull Horn Cutlery Supplier',
        'Buffalo Horn Spacer Manufacturer',
        'Handcrafted Bone Products',
        'Premium Horn Crafts',
        'AS International',
        'buy knife handle scales online',
        'custom knife making supplies',
    ];
    const k = [...keywordsMeta, ...keywordsMeta.map((k) => k.toLowerCase())].join(', ');
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
            {/* Open Graph Meta Tags */}
            <meta property="og:title" content={title} />
            <meta property="og:description" content={description} />
            <meta property="og:image" content={`${host}/assets/image/logo.webp`} />
            <meta property="og:image:width" content="1200" />
            <meta property="og:image:height" content="630" />
            <meta property="og:image:alt" content={title} />
            <meta property="og:url" content={url} />
            <meta property="og:site_name" content="Viking Craft Store" />
            <meta property="og:type" content="website" />
            <meta property="og:locale" content="en_US" />

            {/* Twitter Meta Tags */}
            <meta name="twitter:title" content={title} />
            <meta name="twitter:description" content={description} />
            <meta name="twitter:image" content={`${host}/assets/image/logo.webp`} />
            <meta name="twitter:card" content="summary_large_image" />
            <meta name="twitter:site" content="@vikingcraftstore" />
            
            {/* Canonical URL */}
            <link rel="canonical" href={url} />
            
            {/* Googlebot Specific Directives */}
            <meta name="googlebot" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1" />
            <meta name="googlebot-news" content="index, follow" />
            <meta name="bingbot" content="index, follow" />
            
            {/* hreflang Tags for International SEO */}
            <link rel="alternate" hrefLang="en" href={url} />
            <link rel="alternate" hrefLang="en-US" href={url} />
            <link rel="alternate" hrefLang="en-GB" href={url} />
            <link rel="alternate" hrefLang="x-default" href={url} />
            
            {/* Additional SEO Tags */}
            <meta name="theme-color" content="#24bbdb" />
            <meta name="format-detection" content="telephone=no" />
            <meta httpEquiv="X-UA-Compatible" content="IE=edge" />
            
            {/* Site Verification - Add your codes here */}
            {/* <meta name="google-site-verification" content="YOUR_GOOGLE_VERIFICATION_CODE" /> */}
            {/* <meta name="msvalidate.01" content="YOUR_BING_VERIFICATION_CODE" /> */}
        </Head>
    );
};

export default CommonMeta;
