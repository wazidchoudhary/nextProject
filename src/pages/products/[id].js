import MetaHead from '@/components/MetaHead';
import { FirebaseHelper } from '@/lib/firebase-helpers';
import ProductDetail from '@/components/ProductDetail';
import { CategoryProducts } from '@/components/CategoryProducts';
import ProductSchema from '@/seo/ProductSchema';
export default function (props) {
    const {
        productImage,
        productId,
        productName,
        priceCategory,
        productDescription,
        productCategory,
        productSubCategory,
        productPrice,
        productOldPrice,
        productType,
        productColor,
        productQty,
        productSize,
    } = props.product;
    return (
        <>
            <MetaHead title={props.product.productName} description={props.product.productCategory} />
            <ProductSchema
                product={{
                    name: productName,
                    image: productImage,
                    description: productDescription,
                    category: productCategory,
                    price: productPrice,
                    oldPrice: productOldPrice,
                }}
            />
            <section className="bg-image">
                <h2 className="sr-only">Site Breadcrumb</h2>
                <div className="container" style={{ height: '150px' }}></div>
            </section>
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
