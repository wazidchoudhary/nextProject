import useSelector from '@/hooks/useSelector';
import { selectCartProduct } from '@/selector/cartSelector';
import { CartHelper } from '@/lib/cart';
import BreadCrumb from '@/seo/BreadCrumb';
import React from 'react';

export default function () {
    const cartProducts = useSelector(selectCartProduct)
    console.log(cartProducts)
    const title = 'Cart'
    const breadCrumbItems = [{ url: '/', name: 'Home' }, { name: title }];
    return <>
        <BreadCrumb items={breadCrumbItems} text={title} />
        


    </>;
}
