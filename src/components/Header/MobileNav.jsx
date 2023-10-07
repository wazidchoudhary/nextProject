import Link from 'next/link';
import React, { Fragment, useState } from 'react';

const MobileNav = ({ knifeHandles, createDynamicMenuForMobile, products }) => {
    return (
        <div className="mobile-navigation">
            <nav className="off-canvas-nav">
                <ul className="mobile-menu">
                    <li className="menu-item-has-children flex">
                        <Link className="menu-bold" href="/">
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
                        <Link className="menu-bold" href="/about">
                            About Us
                        </Link>
                    </li>
                    <li>
                        <Link className="menu-bold" href="/contact">
                            Contact Us
                        </Link>
                    </li>
                </ul>
            </nav>
        </div>
    );
};

const Accordian = ({ title, href, children, isExpanded }) => {
    const [expanded, setExpanded] = useState(isExpanded);
    const arrowDirection = expanded ? 'up' : 'down';
    const toggleCollapse = () => setExpanded(!expanded);

    return (
        <Fragment>
        <div className='row'>
            <div className="col-10">
            <Link className="menu-bold" href={href}>
                {title}
            </Link>
            </div>
            <div className="col-2" onClick={toggleCollapse}>
                <i className={`fa fa-arrow-${arrowDirection}`}></i>
            </div>
        </div>
            {expanded && children}
        </Fragment>
    );
};

export default MobileNav;
