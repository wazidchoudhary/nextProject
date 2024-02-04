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
const title = 'AS International – Manufacturer and supplier of handcraft Items.';
const description =
    'Bull Shoe Horn Supplier, Dyed Stabilized Bone Scales Exporter, Water Buffalo Horn Scale Manufacturer, Wooden Knife Handle Manufacturer, Bone Pen Blank Exporter, Bone Hair Pipe Bead Exporter In India, Drinking Horn and Mug Supplier, Wooden Cutlery Manufacturer, Teflon Bone Folder Manufacturer.';
const keywords = '';

const url = 'https://www.teflonbonehorncrafts.com/';
const HOST = 'https://www.teflonbonehorncrafts.com/';
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
                title="AS International – Manufacturer and supplier of handcraft Items."
                description="Bull Shoe Horn Supplier, Dyed Stabilized Bone Scales Exporter, Water Buffalo Horn Scale Manufacturer, Wooden Knife Handle Manufacturer, Bone Pen Blank Exporter, Bone Hair Pipe Bead Exporter In India, Drinking Horn and Mug Supplier, Wooden Cutlery Manufacturer, Teflon Bone Folder Manufacturer."
                keywords=""
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
