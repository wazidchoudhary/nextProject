import MetaHead from "@/components/MetaHead";
import { FirebaseHelper } from "@/lib/firebase-helpers";
import ProductDetail from "@/components/ProductDetail";
export default function (props) {
    // console.log(props,'props working')
    return (
        <>
            <MetaHead title={props.product.productName} description={props.product.productCategory.category} />
            <section className="bg-image">
                <h2 className="sr-only">Site Breadcrumb</h2>
                <div className="container" style={{ height: "150px" }}></div>
            </section>

            <ProductDetail product={props.product} />
        </>
    );
}

//getStaticProps
export const getServerSideProps = async (context) => {
    console.log(context, " @@@context");
    const prodId = context.query.id;
    const res = await FirebaseHelper.fetchSingleProduct(prodId);

    return {
        props: {
            product: res,
        },
    };
};
