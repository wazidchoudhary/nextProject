import Header from "@/components/Header";
import "public/assets/css/plugins.min.css";
import "public/assets/css/main.css";


import "@/styles/globals.css";

export default function App({ Component, pageProps }) {
    return (
        <>
            <div className="site-wrapper" id="top">
                <Header />
                <Component {...pageProps} />
                
            </div>
        </>
    );
}
