import MetaHead from "@/components/MetaHead";

export default function PorductDetail({product}) {
    return <>
        <MetaHead title={product.title} description={product.description} />
    </>
}


//getStaticProps
export const getServerSideProps = async (context) => {
    console.log(context, ' @@@context');
    const prodId = context.query.id;
    const res = await fetch('https://dummyjson.com/products/'+prodId);
    const {products} = await res.json();
  
    return {
      props: {
        products: products
      }
    }
  }