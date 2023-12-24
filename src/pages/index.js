import { Inter } from 'next/font/google';
import CommonMeta from '@/seo/MetaHead';
import HeroSlider from '@/components/HeroSlider';
import Intro from '@/components/Intro';
import FeatureProducts from '@/components/FeatureProducts';
import { FirebaseHelper } from '@/lib/firebase-helpers';

const inter = Inter({ subsets: ['latin'] });

export default function ({ products }) {
    return (
        <>
            <CommonMeta
                title="AS International – Manufacturer and supplier of handcraft Items."
                description="AS International is a company of the people who are perfect craftsmen and genuine manufacturer of so many products made of buffalo and sheep horn, camel and buffalo bone and also of different variety wood"
                keywords=''
            />
            <HeroSlider />
            <Intro />
            <FeatureProducts products={products} />
        </>
    );
}

export const getStaticProps = async (context) => {
    const products = await FirebaseHelper.fetchFeaturedProduct();
    return {
        props: {
            products: products,
        },
        revalidate: 3600,
    };
};
