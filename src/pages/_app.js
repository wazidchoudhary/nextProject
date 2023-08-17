import Header from '@/components/Header'
import '@/styles/globals.css'

export default function App({ Component, pageProps }) {
  return <>
<div className="site-wrapper" id="top">
<Header />
<Component {...pageProps} />
<script src="assets/js/custom.js"></script>
</div>
  </>
}
