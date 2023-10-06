import Link from 'next/link';
import { Fragment } from 'react';

const MOCK_OPTIONS = [
    {
        name: 'Home',
        url: '/',
    },

    {
        name: 'Products',
        url: '/products',
    },
    {
        name: 'some product name',
    },
];

const BreadCrumb = ({ items = [], text = 'AS International' }) => {
    return (
        <div className="siteBreadCrumbWrapper">
            <h1>{text}</h1>
            <ul className="siteBreadCrumb">
                {items.map((opt, key) => (
                    <li key={key} className="brd-list">
                        <Link href={opt.url || '#'} className={`${opt.url ? 'text-primary ellipsis-1' : 'ellipsis-1'}`}>
                            {opt.name}
                        </Link>
                        {opt.url && <div className={''}>/</div>}{' '}
                    </li>
                ))}
            </ul>
        </div>
    );
};

export default BreadCrumb;
