import Header from '@/components/Header/Header';
import { Analytics } from '@vercel/analytics/react';
import NextNProgress from 'nextjs-progressbar';
import { ToastContainer } from 'react-toastify';
import Footer from '@/components/Footer';
import { useRouter } from 'next/router';
import { useEffect } from 'react';


import 'public/assets/css/plugins.min.css';
import 'public/assets/css/main.css';
import '@/styles/globals.css';
import 'react-toastify/dist/ReactToastify.css';
import '@/styles/custom.css';
import '@/styles/orderDetail.css';

export default function App({ Component, pageProps }) {
    const router = useRouter();
    useEffect(() => {
        router.events.on('routeChangeStart', () => {
            // close mobile sidebar
        });
    }, []);
    return (
        <>
            <NextNProgress color="#24bbdb" height={5} />

            <div className="site-wrapper" id="top">
                <Header />
                <Component {...pageProps} />
                <Analytics />
                <ToastContainer theme="dark" position="bottom-right" />
                <Footer />
            </div>
        </>
    );
}
