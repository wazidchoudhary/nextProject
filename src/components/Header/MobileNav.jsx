import Image from 'next/image';
import Link from 'next/link';
import React, { Fragment, useState } from 'react';

const MobileNav = ({ knifeHandles, createDynamicMenuForMobile, products }) => {
    return (
        <Fragment>
            <Image title='Mobile Header Logo' src={'/assets/image/logo2.webp'} alt={`logo`} height={50} width={180} style={{ objectFit: 'contain', marginBottom: '20px' }} />
            <div className="mobile-navigation">
                <nav className="off-canvas-nav">
                    <ul className="mobile-menu">
                        <li className="menu-item-has-children flex">
                            <Link title='Home' className="menu-bold" href="/">
                                Home
                            </Link>
                        </li>
                        <li className="menu-item-has-children">
                            <Accordian title="Knife Handles" href="/knifeHandles">
                                <ul className="sub-menu">{createDynamicMenuForMobile(knifeHandles, 'knifeHandles')}</ul>
                            </Accordian>
                        </li>
                        <li className="menu-item-has-children">
                            <Accordian title="More Products" href="/products">
                                <ul className="sub-menu">{createDynamicMenuForMobile(products, 'moreProducts')}</ul>
                            </Accordian>
                        </li>
                        <li>
                            <Link title='About Us' className="menu-bold" href="/about">
                                About Us
                            </Link>
                        </li>
                        <li>
                            <Link title='Contact Us' className="menu-bold" href="/contact">
                                Contact Us
                            </Link>
                        </li>
                        <li>
                            <Link title='Checkout' className="menu-bold" href="/checkout">
                                Checkout
                            </Link>
                        </li>
                    </ul>
                </nav>
            </div>
        </Fragment>
    );
};

const Accordian = ({ title, href, children, isExpanded }) => {
    const [expanded, setExpanded] = useState(isExpanded);
    const arrowDirection = expanded ? 'up' : 'down';
    const toggleCollapse = () => setExpanded(!expanded);

    return (
        <Fragment>
            <div className="row">
                <div className="col-10">
                    <Link title={title} className="menu-bold" href={href}>
                        {title}
                    </Link>
                </div>
                <div className="col-2" onClick={toggleCollapse}>
                    <i style={{ border: '1px solid gray', padding: '2px' }} className={`fa fa-arrow-${arrowDirection}`}></i>
                </div>
            </div>
            {expanded && children}
        </Fragment>
    );
};

export default MobileNav;
