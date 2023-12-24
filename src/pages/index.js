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
                description="Discover exquisite bone horn and teflon crafts at AS International. Renowned for quality, our craftsmen shape natural materials into timeless handcrafted goods like Bull Shoe Horns, Bone Inlay Boxes, and Guitar Horn Saddles. Embrace elegance and tradition with our sustainable and affordable range"
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
