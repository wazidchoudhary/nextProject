import { useInjector } from '@/hooks/useInjector';
import { Html, Head, Main, NextScript } from 'next/document';
import { useEffect } from 'react';

export default function Document() {
    return (
        <Html lang="en">
            <Head>
                {/* <link href="https://fonts.googleapis.com/css?family=Quicksand" rel="stylesheet" /> */}
            </Head>
            <body>
                <Main />
                <NextScript />
            </body>
        </Html>
    );
}
