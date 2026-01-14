import { Inter } from 'next/font/google';
import CommonMeta from '@/seo/MetaHead';
import HeroSlider from '@/components/HeroSlider';
import Intro from '@/components/Intro';
import FeatureProducts from '@/components/FeatureProducts';
import { FirebaseHelper } from '@/lib/firebase-helpers';
import ProductSchema from '@/seo/ProductSchema';
import { organizationSchema } from '@/seo/organizationSchema';
import { siteNavigationElement } from '@/seo/siteNavigationElement';
import { breadCrumbSchema } from '@/seo/breadCrumbSchema';
import { webPageSchema } from '@/seo/webPageSchema';

const inter = Inter({ subsets: ['latin'] });
const title = 'Viking Craft Store - Premium Handcrafted Bone & Horn Products | AS International';
const description =
    'Shop premium knife handle scales, drinking horns, guitar parts & handcrafted bone horn products. Bull Shoe Horn, Dyed Stabilized Bone Scales, Water Buffalo Horn Scales, Viking Drinking Horns & more. Worldwide shipping from AS International.';
const keywords = 'knife handles, bone scales, horn scales, viking drinking horn, guitar saddle, handcrafted products';

const url = 'https://www.vikingcraftstore.com/';
const HOST = 'https://www.vikingcraftstore.com';
export default function ({ products }) {
    return (
        <>
            <ProductSchema
                product={{
                    id: 'home123',
                    name: 'AS International',
                    image: 'assets/image/logo.webp',
                    description:
                        'Discover exquisite bone horn and teflon crafts at AS International. Renowned for quality, our craftsmen shape natural materials into timeless handcrafted goods like Bull Shoe Horns, Bone Inlay Boxes, and Guitar Horn Saddles. Embrace elegance and tradition with our sustainable and affordable range',
                    category: 'E-commerce website',
                    price: 0,
                }}
            />
            <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: webPageSchema(title, description, url) }} />
            <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: organizationSchema() }} />
            <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: siteNavigationElement() }} />
            <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: breadCrumbSchema(title, HOST, url) }} />
            <CommonMeta
                title="Viking Craft Store - Premium Handcrafted Bone & Horn Products | AS International"
                description="Shop premium knife handle scales, drinking horns, guitar parts & handcrafted bone horn products. Bull Shoe Horn, Dyed Stabilized Bone Scales, Water Buffalo Horn Scales, Viking Drinking Horns & more. Worldwide shipping from AS International."
                keywords="knife handles, bone scales, horn scales, viking drinking horn, guitar saddle, handcrafted products"
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
