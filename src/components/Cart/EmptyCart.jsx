import { Fragment } from "react";
import { EmptyProductSection } from "../EmptyProductSection";

const EmptyCart = () => {
    return (
        <Fragment>
            <EmptyProductSection height={100} width={100} description="Cart Is Empty" fontSize="20px" containerHeight="auto" />
       <a href="/products" tabIndex={0} className="btn btn-primary" >Shop Now</a>
</Fragment>
    )
}

export default EmptyCart;