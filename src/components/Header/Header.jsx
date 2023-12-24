import Link from 'next/link';
import React, { useEffect, useState } from 'react';
import Image from 'next/image';
import { knifeHandles, products } from '@/constants/navbar';
import StrUtils from '@/utils/str-utils';
import Cart from '../Cart';
import useSelector from '@/hooks/useSelector';
import { selectCartProduct } from '@/selector/cartSelector';
import MobileNav from './MobileNav';

const Header = () => {
    const [mobileMenu, setMobileMenu] = useState('');
    const cart = useSelector(selectCartProduct) || [];
    const isEmptyCart = cart.length === 0;

    const createDynamicMenuForDesktop = (prop, pageName) => {
        const keys = Object.keys(prop);
        return keys.map((menuTitle, i) => {
            return (
                <li key={menuTitle + i} className="cus-col-25" style={{ border: 'none' }}>
                    <h3 className="menu-title" style={{ fontSize: '14px', fontWeight: '600' }}>
                        <Link title={StrUtils.normalToSnake(menuTitle)} href={`/${pageName}/${StrUtils.normalToSnake(menuTitle)}`}>{menuTitle}</Link>
                    </h3>
                    <ul className="mega-single-block" style={{ marginBottom: '10px' }}>
                        {prop[menuTitle].map((name, i) => {
                            return (
                                <li key={name + i}>
                                    <Link title={StrUtils.normalToSnake(name)} href={`/${pageName}/${StrUtils.normalToSnake(menuTitle)}/${StrUtils.normalToSnake(name)}`}>{name}</Link>
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
                <li key={menuTitle + i} className="menu-item-has-children">
                    <Link title={StrUtils.normalToSnake(menuTitle)} href={`/${pageName}/${StrUtils.normalToSnake(menuTitle)}`} style={{ fontWeight: '500' }}>
                        {menuTitle}
                    </Link>
                    <ul className="sub-menu">
                        {prop[menuTitle].map((name, i) => {
                            return (
                                <li key={name + 1}>
                                    <Link title={StrUtils.normalToSnake(name)} href={`/${pageName}/${StrUtils.normalToSnake(menuTitle)}/${StrUtils.normalToSnake(name)}`}>{name}</Link>
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
            <header className="site-header ha-header-1 absolute-header sticky-init fixed-header d-lg-block d-none" style={{ position: 'sticky' }}>
                <div className="container-fluid">
                    <div className="row align-items-center">
                        <div className="col-lg-9">
                            <div className="main-navigation">
                                <ul className="main-menu @@menuColor">
                                    <li key={'logoHeader'}>
                                        <Image title='Header logo' src={'/assets/image/logo2.webp'} alt={`logo`} height={45} width={150} style={{ objectFit: 'contain' }} />
                                    </li>
                                    <li className="menu-item has-children">
                                        <Link title='Home' href="/">Home</Link>
                                    </li>
                                    {/* Shop */}
                                    <li className="menu-item has-children mega-menu">
                                        <Link title='Knife Handles' href={'/knifeHandles'}>Knife Handles</Link>
                                        <ul className="sub-menu four-column">{createDynamicMenuForDesktop(knifeHandles, 'knifeHandles')}</ul>
                                    </li>
                                    <li className="menu-item has-children mega-menu">
                                        <Link title='More Products' href={'/products'}>More Products</Link>
                                        <ul className="sub-menu four-column">{createDynamicMenuForDesktop(products, 'moreProducts')}</ul>
                                    </li>
                                    {/* Pages */}
                                    <li className="menu-item has-children">
                                        <Link title='About Us' href="/about">About Us</Link>
                                    </li>
                                    <li className="menu-item has-children">
                                        <Link title='Contact Us' href="/contact">Contact Us</Link>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        {/* <div className="col-lg-2 col-xl-2 offset-lg-1 offset-xl-0">
                            <div className="site-brand">
                               
                            </div>
                        </div> */}
                        <div className="col-lg-3 col-xl-3">
                            <div className="header-top-widget">
                                <ul className="header-links">
                                    <li key={'cartAreakey'} className="sin-link">
                                        <Link title="search-bar" href="/search" className="search-trigger link-icon">
                                            <i className="ion-ios-search-strong" />
                                        </Link>
                                    </li>
                                    <li key={'bagkey'} className="sin-link">
                                        <Link title='Cart' href="/cart" className="cart-link link-icon">
                                            <i className="ion-bag" />
                                            {!isEmptyCart && (
                                                <span className="position-absolute translate-bottom badge rounded-pill bg-danger" style={{ fontSize: '10px', top: '14px', left: '60%' }}>
                                                    {cart.length}
                                                    <span className="visually-hidden">unread messages</span>
                                                </span>
                                            )}
                                        </Link>
                                        <Cart cart={cart} />
                                    </li>
                                    <li key={'languagekey'} className="sin-link">
                                        <a title='hamburgur-icon' href="javascript:" className="link-icon hamburgur-icon">
                                            <i className="ion-navicon" />
                                        </a>
                                        <div className="sin-dropdown option-dropdown">
                                            <div className="inner-single-block">
                                                <h4 className="option-title">my account</h4>
                                                <ul className="option-list">
                                                    <li>
                                                        <Link title='Cart' href="'/cart">Cart</Link>
                                                    </li>
                                                    <li>
                                                        <Link title='Checkout' href="/checkout">Checkout</Link>
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

            <header className="mobile-header d-lg-none absolute-header" style={{ position: 'sticky' }}>
                <div className="container">
                    <div className="row align-items-end">
                        <div className="col-md-4 col-7"></div>
                        <div className="col-md-8 col-5 text-end">
                            <div className="mobile-header-btns header-top-widget ">
                                <ul className="header-links">
                                    <li key={'cartAreakey'} className="sin-link">
                                        <Link title='Search' href="/search" className="search-trigger link-icon">
                                            <i className="ion-ios-search-strong" />
                                        </Link>
                                    </li>
                                    <li className="sin-link">
                                        <Link title='Cart' href="/cart" className="cart-link link-icon">
                                            <i className="ion-bag" />
                                            {!isEmptyCart && (
                                                <span className="position-absolute translate-bottom badge rounded-pill bg-danger" style={{ fontSize: '10px', top: '14px', left: '60%' }}>
                                                    {cart.length}
                                                    <span className="visually-hidden">unread messages</span>
                                                </span>
                                            )}
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
                    <MobileNav knifeHandles={knifeHandles} createDynamicMenuForMobile={createDynamicMenuForMobile} products={products} />
                </div>
            </aside>
        </>
    );
};

export default Header;
