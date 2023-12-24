import CommonMeta from '@/seo/MetaHead';
import { FirebaseHelper } from '@/lib/firebase-helpers';
import ProductDetail from '@/components/ProductDetail';
import { CategoryProducts } from '@/components/CategoryProducts';
import ProductSchema from '@/seo/ProductSchema';
import BreadCrumb from '@/seo/BreadCrumb';
export default function (props) {
    const { productId, productImage, productName, productDescription, productCategory, productPrice, productOldPrice } = props.product;
    const breadCrumbItems = [{ url: '/', name: 'Home' }, { url: '/products', name: 'Products' }, { name: productName }];

    return (
        <>
            <CommonMeta title={props.product.productName} description={props.product.productCategory} />
           
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
