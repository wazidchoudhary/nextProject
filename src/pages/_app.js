import Header from '@/components/Header';
import 'public/assets/css/plugins.min.css';
import 'public/assets/css/main.css';
import { Analytics } from '@vercel/analytics/react';

import '@/styles/globals.css';
import NextNProgress from 'nextjs-progressbar';


export default function App({ Component, pageProps }) {
   
    return (
        <>
            <NextNProgress color="#24bbdb" height={5} />

            <div className="site-wrapper" id="top">
                <Header />
                <Component {...pageProps} />
                <Analytics />
            </div>
        </>
    );
}
