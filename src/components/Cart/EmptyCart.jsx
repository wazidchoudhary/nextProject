import { Fragment } from 'react';
import { EmptyProductSection } from '../EmptyProductSection';
import { useRouter } from 'next/router';
const EmptyCart = () => {
    const router = useRouter();
    return (
        <Fragment>
            <EmptyProductSection height={100} width={100} description="Cart Is Empty" fontSize="20px" containerHeight="auto" />

            <button type="button" onClick={() => router.push('/products')} className="btn btn-primary">
                Shop Now
            </button>
        </Fragment>
    );
};

export default EmptyCart;
