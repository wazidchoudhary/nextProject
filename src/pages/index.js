import Head from 'next/head'
import Image from 'next/image'
import { Inter } from 'next/font/google'
import styles from '@/styles/Home.module.css'
import MetaHead from '@/components/MetaHead'
import HeroSlider from '@/components/HeroSlider'
import Intro from '@/components/Intro'
import FeatureProducts from '@/components/FeatureProducts'

const inter = Inter({ subsets: ['latin'] })

export default function Home({products}) {
  console.log(products, ' @@@ products')
  return (
    <>
    <MetaHead title="AS International – Manufacturer and wholesale supplier of all type of handicraft Products." description="AS International is a company of the people who are perfect craftsmen and genuine manufacturer of so many products made of buffalo and sheep horn, camel and buffalo bone and also of different variety wood" />
    <HeroSlider />
    <Intro />
    <FeatureProducts />
    </>
  )
}

export const getServerSideProps = async (context) => {
  console.log(context, ' @@@context');
  const res = await fetch('https://dummyjson.com/products');
  const {products} = await res.json();

  return {
    props: {
      products: products
    }
  }
}