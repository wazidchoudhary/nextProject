import { Html, Head, Main, NextScript } from 'next/document';

export default function Document() {
    return (
        <Html lang="en">
            <Head></Head>
            <body>
                <Main />
                <NextScript />
                {/* <script src="/assets/js/plugins.min.js"></script> */}
                {/* <script src="/assets/js/ajax-mail.js"></script> */}
                <script src="/assets/js/custom.js"></script>
            </body>
        </Html>
    );
}
