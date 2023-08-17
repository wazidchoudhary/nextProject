import { Html, Head, Main, NextScript } from 'next/document'

export default function Document() {
  return (
    <Html lang="en">
      <Head>
      <link rel="stylesheet" type="text/css" media="screen" href="assets/css/plugins.min.css"/>
    <link rel="stylesheet" type="text/css" media="screen" href="assets/css/main.css"/>
      </Head>
      <body>
        <Main />
        <NextScript />
      </body>
    </Html>
  )
}
