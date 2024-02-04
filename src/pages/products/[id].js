import CommonMeta from '@/seo/MetaHead';
import { FirebaseHelper } from '@/lib/firebase-helpers';
import ProductDetail from '@/components/ProductDetail';
import { CategoryProducts } from '@/components/CategoryProducts';
import ProductSchema from '@/seo/ProductSchema';
import BreadCrumb from '@/seo/BreadCrumb';
import { webPageSchema } from '@/seo/webPageSchema';
import { organizationSchema } from '@/seo/organizationSchema';
import { siteNavigationElement } from '@/seo/siteNavigationElement';
import { breadCrumbSchema } from '@/seo/breadCrumbSchema';
export default function (props) {
    const { productId, productImage, productName, productDescription, productCategory, productPrice, productOldPrice } = props.product;
    const breadCrumbItems = [{ url: '/', name: 'Home' }, { url: '/products', name: 'Products' }, { name: productName }];
    const descString = productDescription.replace(/<\/?[^>]+(>|$)/g, "")
    const title = props.product.productName.split('-')[0]
    const description = descString.substring(0, 315)
    const HOST = 'http://www.teflonbonehorncrafts.com/';
    const url = 'http://www.teflonbonehorncrafts.com/products/'+productId
    return (
        <>
            <CommonMeta title={`${props.product.productName.split('-')[0]} - AS INTERNATIONAL`} description={descString.substring(0, 315)} />
           
            <ProductSchema
                product={{
                    id: productId,
                    name: productName,
                    image: productImage,
                    description: productDescription,
                    category: productCategory,
                    price: productPrice,
                    oldPrice: productOldPrice,
                }}
            />
            <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: webPageSchema(title, description, url) }} />
            <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: organizationSchema() }} />
            <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: siteNavigationElement() }} />
            <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: breadCrumbSchema(title, HOST, url) }} />
            <BreadCrumb items={breadCrumbItems} text={productName} />
            <main className="inner-page-sec-padding pb-0">
                <ProductDetail product={props.product} />
                <CategoryProducts categoryProducts={props.categoryProducts} />
            </main>
        </>
    );
}

//getStaticProps
export const getServerSideProps = async (context) => {
    const prodId = context.query.id;
    const response = await FirebaseHelper.fetchSingleProduct(prodId);
    const categoryProducts = await FirebaseHelper.fetchProductByCategoryLimit(response.productCategory, 5);

    return {
        props: {
            product: response,
            categoryProducts: categoryProducts.filter((res) => res.productId !== response.productId),
        },
    };
};
