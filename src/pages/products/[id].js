import MetaHead from "@/components/MetaHead";
import { FirebaseHelper } from "@/lib/firebase-helpers";
import ProductDetail from "@/components/ProductDetail";
import { CategoryProducts } from "@/components/CategoryProducts";
export default function (props) {

    return (
        <>
            <MetaHead title={props.product.productName} description={props.product.productCategory.category} />
            <section className="bg-image">
                <h2 className="sr-only">Site Breadcrumb</h2>
                <div className="container" style={{ height: "150px" }}></div>
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
    console.log(context, " @@@context");
    const prodId = context.query.id;
    const response = await FirebaseHelper.fetchSingleProduct(prodId);
    const categoryProducts = await FirebaseHelper.fetchProductByCategoryLimit(response.productCategory,5)

    return {
        props: {
            product: response,
            categoryProducts:categoryProducts.filter((res)=>res.productId !== response.productId)
        },
    };
};
