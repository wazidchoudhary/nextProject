import BreadCrumb from '@/seo/BreadCrumb';
import React from 'react';

export default function () {
    const title = 'Checkout'
    const breadCrumbItems = [{ url: '/', name: 'Home' }, { name: title }];
    return <>
    <BreadCrumb items={breadCrumbItems} text={title} />
    <div style={{marginTop:'10px'}} />
    </>;
}
