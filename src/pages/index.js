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
                description="Discover the artistry of AS International, the home of exquisite bone horn crafts and teflon bone horn creations. As a renowned manufacturer and supplier, we take pride in our skilled craftsmen who expertly shape buffalo and sheep horn, camel and buffalo bone, and a variety of woods into timeless pieces. Explore our diverse catalog featuring everything from the practicality of Bull Shoe Horns and Water Buffalo Horn Scales to the intricate beauty of Bone Inlay Jewellery Boxes and Wooden Combs. Our dedication to quality and craftsmanship shines through in every item, including our sought-after Dyed Stabilized Bone Scales, Buffalo Horn Plates, and unique Guitar Horn Saddles – all crafted with the utmost care and precision. With a commitment to sustainable practices and the genuine craft, AS International stands out as a leading exporter in India, offering an affordable range of horn crafts without compromising on quality. Embrace the elegance of handcrafted goods with our collection that includes Drinking Horns, Mugs, Wooden Cutlery, and more. Experience the blend of tradition and innovation in every Bone Pen Blank, Hair Pipe Bead, and Bull Horn Cutlery piece we supply"
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
