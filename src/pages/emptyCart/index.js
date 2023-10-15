import BreadCrumb from '@/seo/BreadCrumb';
import Container from '@/components/Layout/Container';
import { useEffect, useState } from 'react';
import { EmptyProductSection } from '@/components/EmptyProductSection';
import { CartHelper } from '@/lib/cart';

export default function ({}) {
    const title = 'Empty Cart';
    const breadCrumbItems = [{ url: '/', name: 'Home' }, { name: title }];

    useEffect(() => {
        CartHelper.emptyCart();
    }, []);

    return (
        <>
            <BreadCrumb items={breadCrumbItems} text={title} />
            <Container>{<EmptyProductSection containerHeight="auto" description={'Empty Cart'} />}</Container>
        </>
    );
}
