import Link from 'next/link';
import React, { useEffect, useState } from 'react';
import Image from 'next/image';
import { knifeHandles, products } from '@/constants/navbar';
import StrUtils from '@/utils/str-utils';

import Cart from './Cart';
const Header = () => {

    // const cartData = useSelector('cart');
    const [mobileMenu, setMobileMenu] = useState('');
    const createDynamicMenuForDesktop = (prop, pageName) => {
        const keys = Object.keys(prop);
        return keys.map((menuTitle, i) => {
            return (
                <li key={menuTitle + i} className="cus-col-25" style={{ border: 'none' }}>
                    <h3 className="menu-title" style={{ fontSize: '14px',fontWeight:'600' }}>
                        <Link href={`/${pageName}/${StrUtils.normalToSnake(menuTitle)}`}>{menuTitle}</Link>
                    </h3>
                    <ul className="mega-single-block" style={{ marginBottom: '10px' }}>
                        {prop[menuTitle].map((name, i) => {
                            return (
                                <li key={name + i}>
                                    <Link href={`/${pageName}/${StrUtils.normalToSnake(menuTitle)}/${StrUtils.normalToSnake(name)}`}>{name}</Link>
                                </li>
                            );
                        })}
                    </ul>
                </li>
            );
        });
    };

    const createDynamicMenuForMobile = (prop, pageName) => {
        const keys = Object.keys(prop);
        return keys.map((menuTitle, i) => {
            return (
                <li key={menuTitle + i} className="menu-item-has-children" >
                    <Link href={`/${pageName}/${StrUtils.normalToSnake(menuTitle)}`} style={{fontWeight:'500'}}>{menuTitle}</Link>
                    <ul className="sub-menu" >
                        {prop[menuTitle].map((name, i) => {
                            return (
                                <li key={name + 1}>
                                    <Link href={`/${pageName}/${StrUtils.normalToSnake(menuTitle)}/${StrUtils.normalToSnake(name)}`}>{name}</Link>
                                </li>
                            );
                        })}
                    </ul>
                </li>
            );
        });
    };

    return (
        <>
            <header className="site-header ha-header-1 absolute-header sticky-init fixed-header d-lg-block d-none">
                <div className="container-fluid">
                    <div className="row align-items-center">
                        <div className="col-lg-7">
                            <div className="main-navigation">
                                <ul className="main-menu @@menuColor">
                                    <li key={'logoHeader'}>
                                        <Image src={'/assets/image/Logo2.png'} alt={`logo`} height={45} width={150} style={{ objectFit: 'contain' }} />
                                    </li>
                                    <li className="menu-item has-children">
                                        <Link href="/">Home</Link>
                                    </li>
                                    {/* Shop */}
                                    <li className="menu-item has-children mega-menu">
                                        <Link href={'/knifeHandles'}>Knife Handles</Link>
                                        <ul className="sub-menu four-column">{createDynamicMenuForDesktop(knifeHandles, 'knifeHandles')}</ul>
                                    </li>
                                    <li className="menu-item has-children mega-menu">
                                        <Link href={'/products'}>More Products</Link>
                                        <ul className="sub-menu four-column">{createDynamicMenuForDesktop(products, 'moreProducts')}</ul>
                                    </li>
                                    {/* Pages */}
                                    <li className="menu-item has-children">
                                        <Link  href="/about">About Us</Link>
                                    </li>
                                    <li className="menu-item has-children">
                                        <Link href="/contact">Contact Us</Link>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div className="col-lg-2 col-xl-2 offset-lg-1 offset-xl-0">
                            <div className="site-brand">
                                <Link href="/home">{/* <img src="assets/image/IMG_20230720_114137-removebg-preview.png" /> */}</Link>
                            </div>
                        </div>
                        <div className="col-lg-3 col-xl-3">
                            <div className="header-top-widget">
                                <ul className="header-links">
                                    <li key={'cartAreakey'} className="sin-link">
                                        <a href="javascript:" className="search-trigger link-icon">
                                            <i className="ion-ios-search-strong" />
                                        </a>
                                    </li>
                                    <li key={'bagkey'} className="sin-link">
                                        {/* <Link href="/cart" className="cart-link link-icon"> */}
                                            <i className="ion-bag" />
                                        {/* </Link> */}
                                       <Cart />
                                    </li>
                                    <li key={'languagekey'} className="sin-link">
                                        <a href="javascript:" className="link-icon hamburgur-icon">
                                            <i className="ion-navicon" />
                                        </a>
                                        <div className="sin-dropdown option-dropdown">
                                            
                                            <div className="inner-single-block">
                                                <h4 className="option-title">my account</h4>
                                                <ul className="option-list">
                                                    <li>
                                                        <Link href="'/cart">Cart</Link>
                                                    </li>
                                                    <li>
                                                        <Link href="/checkout">Checkout</Link>
                                                    </li>
                                                  
                                                </ul>
                                            </div>
                                        </div>
                                    </li>
                                </ul>
                                {/* <div className="search-wrapper">
                                    <div className="search-wrapper-inner">
                                        <div className="container">
                                            <button className="search-dismiss">
                                                <i className="fas fa-times" />
                                            </button>
                                            <form action="#">
                                                <div className="search-box">
                                                    <input type="text" placeholder="Search Our catalog" />
                                                    <button className="search-button">
                                                        <i className="fas fa-search" />
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div> */}
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <header className="mobile-header d-lg-none absolute-header">
                <div className="container">
                    <div className="row align-items-end">
                        <div className="col-md-4 col-7">
                            {/* <a href="index.html" className="site-brand"></a> */}
                        </div>
                        <div className="col-md-8 col-5 text-end">
                            <div className="mobile-header-btns header-top-widget ">
                                <ul className="header-links">
                                    <li className="sin-link">
                                        <Link href="/cart" className="cart-link link-icon">
                                            <i className="ion-bag" />
                                        </Link>
                                    </li>
                                    <li className="sin-link">
                                        <div
                                            onClick={() => {
                                                setMobileMenu('open');
                                            }}
                                           
                                            className="link-icon hamburgur-icon off-canvas-btn"
                                        >
                                            <i className="ion-navicon" />
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            <aside className={`off-canvas-wrapper ${mobileMenu}`}>
                <div
                    className="btn-close-off-canvas"
                    onClick={() => {
                        setMobileMenu('');
                    }}
                >
                    <i className="ion-android-close" />
                </div>
                <div className="off-canvas-inner">
                    {/* search box start */}
                    <div className="search-box offcanvas">
                        <form>
                            <input type="text" placeholder="Search Here" />
                            <button className="search-btn">
                                <i className="ion-ios-search-strong" />
                            </button>
                        </form>
                    </div>

                    {/* search box end */}
                    {/* mobile menu start */}
                    <div className="mobile-navigation">
                        <nav className="off-canvas-nav">
                            <ul className="mobile-menu">
                                <li className="menu-item-has-children flex">
                                    <Link className='menu-bold' href="/">Home</Link>
                                </li>
                                <li className="menu-item-has-children">
                                    <Link className='menu-bold' href={'/knifeHandles'}>Knife Handles</Link>
                                    <ul className="sub-menu">{createDynamicMenuForMobile(knifeHandles, 'knifeHandles')}</ul>
                                </li>
                                <li className="menu-item-has-children">
                                    <Link className='menu-bold' href={'/products'}>More Products</Link>
                                    <ul className="sub-menu">{createDynamicMenuForMobile(products, 'moreProducts')}</ul>
                                </li>
                                <li >
                                        <Link className='menu-bold' href="/about">About Us</Link>
                                    </li>
                                    <li >
                                        <Link className='menu-bold' href="/contact">Contact Us</Link>
                                    </li>
                            </ul>
                        </nav>
                    </div>
                    {/* [mobile menu end */}
                  
                </div>
            </aside>
            
        </>
    );
};

export default Header;
