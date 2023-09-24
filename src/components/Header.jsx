import Link from "next/link";
import React, { useEffect, useState } from "react";
import Image from "next/image";
import { knifeHandles, products } from "@/constants/navbar";
import StrUtils from "@/utils/str-utils";
import store from "@/lib/store/store";
import useSelector from "@/hooks/useSelector";
const Header = () => {
    const { cart = [] } = useSelector("*");
    const cartData = useSelector("cart");
    const [mobileMenu,setMobileMenu] = useState('')
    const createDynamicMenu = (prop, pageName) => {
        const keys = Object.keys(prop);
        return keys.map((menuTitle, i) => {
            return (
                <li key={menuTitle + i} className="cus-col-25" style={{ border: "none" }}>
                    <h3 className="menu-title" style={{ fontSize: "14px" }}>
                        <Link href={`/${pageName}/${StrUtils.normalToSnake(menuTitle)}`}>{menuTitle}</Link>
                    </h3>
                    <ul className="mega-single-block" style={{ marginBottom: "10px" }}>
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

    return (
        <>
                <header className="site-header ha-header-1 absolute-header sticky-init fixed-header d-lg-block d-none">
                    <div className="container-fluid">
                        <div className="row align-items-center">
                            <div className="col-lg-7">
                                <div className="main-navigation">
                                    <ul className="main-menu @@menuColor">
                                        <li key={"logoHeader"}>
                                            <img
                                                src={"/assets/image/logo2.png"}
                                                alt={`logo`}
                                                height={45}
                                                width={150}
                                                priority
                                                style={{objectFit:"contain"}}
                                              
                                            />
                                        </li>
                                        <li key={"homeHeader"} className="menu-item has-children">
                                            <Link href="/">Home</Link>
                                        </li>
                                        {/* Shop */}
                                        <li key={"knifeHandlesNav"} className="menu-item has-children mega-menu">
                                            <Link href={"/knifeHandles"}>Knife Handles</Link>
                                            <ul className="sub-menu four-column">{createDynamicMenu(knifeHandles, "knifeHandles")}</ul>
                                        </li>
                                        <li key={"moreproductskey"} className="menu-item has-children mega-menu">
                                            <Link href={"/products"}>More Products</Link>
                                            <ul className="sub-menu four-column">{createDynamicMenu(products, "moreProducts")}</ul>
                                        </li>
                                        {/* Pages */}
                                        <li key={"aboutuskey"} className="menu-item has-children">
                                            <Link href="/about">About Us</Link>
                                        </li>
                                        <li key={"contactuskey"} className="menu-item has-children">
                                            <Link href="/contact">Contact Us</Link>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div className="col-lg-2 col-xl-2 offset-lg-1 offset-xl-0">
                                <div className="site-brand">
                                    <a href="index.html">{/* <img src="assets/image/IMG_20230720_114137-removebg-preview.png" /> */}</a>
                                </div>
                            </div>
                            <div className="col-lg-3 col-xl-3">
                                <div className="header-top-widget">
                                    <ul className="header-links">
                                        <li key={"cartAreakey"} className="sin-link">
                                            <a href="javascript:" className="search-trigger link-icon">
                                                <i className="ion-ios-search-strong" />
                                            </a>
                                        </li>
                                        <li key={"bagkey"} className="sin-link">
                                            <a href="javascript:" className="cart-link link-icon">
                                                <i className="ion-bag" />
                                            </a>
                                            <div className="sin-dropdown cart-dropdown">
                                                <div className="inner-single-block">
                                                    <div className="cart-product">
                                                        <div className="icon">
                                                            {/* <img src="image/products/home-1/cart-product-1.jpg" /> */}
                                                            <div className="product-badge-3">1x</div>
                                                        </div>
                                                        <div className="description">
                                                            <h4>Yuqidong Sudaderas</h4>
                                                            <span className="price">€500.00</span>
                                                            <ul className="attr-content">
                                                                <li>
                                                                    <span>size :</span> S
                                                                </li>
                                                                <li>
                                                                    <span>color :</span> Beige
                                                                </li>
                                                            </ul>
                                                        </div>
                                                        <a href="index.html#" className="cart-item-cross">
                                                            <i className="fas fa-times" />
                                                        </a>
                                                    </div>
                                                </div>
                                                <div className="inner-single-block">
                                                    <ul className="cart-details">
                                                        <li>
                                                            <span className="label">Subtotal</span> <span className="value">€500.00</span>
                                                        </li>
                                                        <li>
                                                            <span className="label">Shipping</span> <span className="value">€7.00</span>
                                                        </li>
                                                        <li>
                                                            <span className="label">Taxes</span> <span className="value">€0.00</span>
                                                        </li>
                                                        <li>
                                                            <span className="label">Total</span> <span className="value">€507.00</span>
                                                        </li>
                                                    </ul>
                                                </div>
                                                <div className="inner-single-block">
                                                    <a href="checkout.html" className="btn w-100">
                                                        Checkout
                                                    </a>
                                                </div>
                                                <ul>
                                                    {cart.map((cart) => {
                                                        return (
                                                            <li>
                                                                {cart.productName} - {cart.quantity}
                                                            </li>
                                                        );
                                                    })}
                                                </ul>
                                            </div>
                                        </li>
                                        <li key={"languagekey"} className="sin-link">
                                            <a href="javascript:" className="link-icon hamburgur-icon">
                                                <i className="ion-navicon" />
                                            </a>
                                            <div className="sin-dropdown option-dropdown">
                                                <div className="inner-single-block">
                                                    <h4 className="option-title">
                                                        English <i className="fas fa-angle-down" />
                                                    </h4>
                                                    <ul className="option-list">
                                                        <li>
                                                            <a>English</a>
                                                        </li>
                                                        <li>
                                                            <a>اللغة العربية</a>
                                                        </li>
                                                    </ul>
                                                </div>
                                                <div className="inner-single-block">
                                                    <h4 className="option-title">
                                                        EUR <i className="fas fa-angle-down" />
                                                    </h4>
                                                    <ul className="option-list">
                                                        <li>
                                                            <a>€ Euro</a>
                                                        </li>
                                                        <li>
                                                            <a>$ US Dollar</a>
                                                        </li>
                                                    </ul>
                                                </div>
                                                <div className="inner-single-block">
                                                    <h4 className="option-title">my account</h4>
                                                    <ul className="option-list">
                                                        <li>
                                                            <a href="my-account.html">My account</a>
                                                        </li>
                                                        <li>
                                                            <a href="checkout.html">Checkout</a>
                                                        </li>
                                                        <li>
                                                            <a href="login-register.html">Sign in</a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </li>
                                    </ul>
                                    <div className="search-wrapper">
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
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                </header>
                
                            
                     

                <header className="mobile-header d-lg-none absolute-header">
                    <div className="container">
                        <div className="row align-items-end">
                            <div className="col-md-4 col-7">
                                <a href="index.html" className="site-brand">
                                   
                                </a>
                            </div>
                            <div className="col-md-8 col-5 text-end">
                                <div className="mobile-header-btns header-top-widget ">
                                    <ul className="header-links">
                                        <li className="sin-link">
                                            <a href="cart.html" className="cart-link link-icon">
                                                <i className="ion-bag" />
                                            </a>
                                        </li>
                                        <li className="sin-link" >
                                            <a onClick={()=>{setMobileMenu('open')}} href="javascript:" className="link-icon hamburgur-icon off-canvas-btn">
                                                <i className="ion-navicon" />
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </header>
                <aside className={`off-canvas-wrapper ${mobileMenu}`}>
                    <div className="btn-close-off-canvas" onClick={()=>{setMobileMenu('')}}>
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
                                    <li  className="menu-item-has-children">
                                        <a href="index.html#">Home</a>
                                        <ul className="sub-menu">
                                            <li>
                                                <a href="index.html">Home One</a>
                                            </li>
                                            <li>
                                                <a href="index-2.html">Home Two</a>
                                            </li>
                                            <li>
                                                <a href="index-3.html">Home Three</a>
                                            </li>
                                            <li>
                                                <a href="index-4.html">Home Four</a>
                                            </li>
                                            <li>
                                                <a href="index-5.html">Home Five</a>
                                            </li>
                                        </ul>
                                    </li>
                                    <li className="menu-item-has-children">
                                        <a href="index.html#">Blog</a>
                                        <ul className="sub-menu">
                                            <li className="menu-item-has-children">
                                                <a href="index.html#">Blog Grid</a>
                                                <ul className="sub-menu">
                                                    <li>
                                                        <a href="blog.html">Full Widh (Default)</a>
                                                    </li>
                                                    <li>
                                                        <a href="blog-left-sidebar.html">left Sidebar</a>
                                                    </li>
                                                    <li>
                                                        <a href="blog-right-sidebar.html">Right Sidebar</a>
                                                    </li>
                                                </ul>
                                            </li>
                                            <li className="menu-item-has-children">
                                                <a href="index.html#">Blog List</a>
                                                <ul className="sub-menu">
                                                    <li>
                                                        <a href="blog-list.html">Full Widh (Default)</a>
                                                    </li>
                                                    <li>
                                                        <a href="blog-list-left-sidebar.html">left Sidebar</a>
                                                    </li>
                                                    <li>
                                                        <a href="blog-list-right-sidebar.html">Right Sidebar</a>
                                                    </li>
                                                </ul>
                                            </li>
                                            <li className="menu-item-has-children">
                                                <a href="index.html#">Blog Details</a>
                                                <ul className="sub-menu">
                                                    <li>
                                                        <a href="blog-details.html">Image Format (Default)</a>
                                                    </li>
                                                    <li>
                                                        <a href="blog-details-gallery.html">Gallery Format</a>
                                                    </li>
                                                    <li>
                                                        <a href="blog-details-audio.html">Audio Format</a>
                                                    </li>
                                                    <li>
                                                        <a href="blog-details-video.html">Video Format</a>
                                                    </li>
                                                    <li>
                                                        <a href="blog-details-left-sidebar.html">left Sidebar</a>
                                                    </li>
                                                </ul>
                                            </li>
                                        </ul>
                                    </li>
                                    <li className="menu-item-has-children">
                                        <a href="index.html#">Shop</a>
                                        <ul className="sub-menu">
                                            <li className="menu-item-has-children">
                                                <a href="index.html#">Shop Grid</a>
                                                <ul className="sub-menu">
                                                    <li>
                                                        <a href="shop-grid.html">Fullwidth</a>
                                                    </li>
                                                    <li>
                                                        <a href="shop-grid--box-layout.html">Fullwidth (box layout)</a>
                                                    </li>
                                                    <li>
                                                        <a href="shop-grid-left-sidebar.html">left Sidebar</a>
                                                    </li>
                                                    <li>
                                                        <a href="shop-grid-left-sidebar--box-layout.html">left Sidebar(box layout)</a>
                                                    </li>
                                                    <li>
                                                        <a href="shop-grid-right-sidebar--box-layout.html">Right Sidebar</a>
                                                    </li>
                                                    <li>
                                                        <a href="shop-grid-right-sidebar.html">Right Sidebar(box layout)</a>
                                                    </li>
                                                </ul>
                                            </li>
                                            <li className="menu-item-has-children">
                                                <a href="index.html#">Shop List</a>
                                                <ul className="sub-menu">
                                                    <li>
                                                        <a href="shop-list.html">Fullwidth</a>
                                                    </li>
                                                    <li>
                                                        <a href="shop-list--box-layout.html">Fullwidth (box layout)</a>
                                                    </li>
                                                    <li>
                                                        <a href="shop-list-left-sidebar.html">left Sidebar</a>
                                                    </li>
                                                    <li>
                                                        <a href="shop-list-left-sidebar--box-layout.html">left Sidebar(box layout)</a>
                                                    </li>
                                                    <li>
                                                        <a href="shop-list-right-sidebar.html">Right Sidebar</a>
                                                    </li>
                                                    <li>
                                                        <a href="shop-list-right-sidebar--box-layout.html">Right Sidebar(box layout)</a>
                                                    </li>
                                                </ul>
                                            </li>
                                            <li className="menu-item-has-children">
                                                <a href="index.html#">Product Details 1</a>
                                                <ul className="sub-menu">
                                                    <li>
                                                        <a href="product-details.html">Product Details Page</a>
                                                    </li>
                                                    <li>
                                                        <a href="product-details-affiliate.html">Product Details Affiliate</a>
                                                    </li>
                                                    <li>
                                                        <a href="product-details-group.html">Product Details Group</a>
                                                    </li>
                                                    <li>
                                                        <a href="product-details-variable.html">Product Details Variables</a>
                                                    </li>
                                                </ul>
                                            </li>
                                            <li className="menu-item-has-children">
                                                <a href="index.html#">Product Details 2</a>
                                                <ul className="sub-menu">
                                                    <li>
                                                        <a href="product-details-left-thumbnail.html">left Thumbnail</a>
                                                    </li>
                                                    <li>
                                                        <a href="product-details-right-thumbnail.html">Right Thumbnail</a>
                                                    </li>
                                                    <li>
                                                        <a href="product-details-left-gallery.html">Left Gallery</a>
                                                    </li>
                                                    <li>
                                                        <a href="product-details-right-gallery.html">Right Gallery</a>
                                                    </li>
                                                </ul>
                                            </li>
                                        </ul>
                                    </li>
                                    <li className="menu-item-has-children">
                                        <a href="index.html#">Pages</a>
                                        <ul className="sub-menu">
                                            <li>
                                                <a href="cart.html">Cart</a>
                                            </li>
                                            <li>
                                                <a href="checkout.html">Checkout</a>
                                            </li>
                                            <li>
                                                <a href="compare.html">Compare</a>
                                            </li>
                                            <li>
                                                <a href="wishlist.html">Wishlist</a>
                                            </li>
                                            <li>
                                                <a href="login-register.html">Login Register</a>
                                            </li>
                                            <li>
                                                <a href="my-account.html">My Account</a>
                                            </li>
                                        </ul>
                                    </li>
                                    <li>
                                        <a href="contact.html">Contact</a>
                                    </li>
                                </ul>
                            </nav>
                         
                        </div>
                        {/* mobile menu end */}
                        {/* <nav className="off-canvas-nav">
                            <ul className="mobile-menu currency-menu">
                                <li className="menu-item-has-children">
                                    <a href="index.html#">
                                        Currency - USD $ <i className="fas fa-angle-down" />
                                    </a>
                                    <ul className="sub-menu">
                                        <li>
                                            <a href="cart.html">USD $</a>
                                        </li>
                                        <li>
                                            <a href="checkout.html">EUR €</a>
                                        </li>
                                    </ul>
                                </li>
                                <li className="menu-item-has-children">
                                    <a href="index.html#">
                                        Lang - Eng
                                        <i className="fas fa-angle-down" />
                                    </a>
                                    <ul className="sub-menu">
                                        <li>Eng</li>
                                        <li>Ban</li>
                                    </ul>
                                </li>
                            </ul>
                        </nav> */}
                        {/* <div className="off-canvas-bottom">
                            <div className="contact-list mb--10">
                                <a className="sin-contact">
                                    <i className="fas fa-mobile-alt" />
                                    (12345) 78790220
                                </a>
                                <a className="sin-contact">
                                    <i className="fas fa-envelope" />
                                    examle@handart.com
                                </a>
                            </div>
                            <div className="header-social social-normal">
                                <a href="index.html#" className="single-icon">
                                    <i className="fab fa-facebook-f" />
                                </a>
                                <a href="index.html#" className="single-icon">
                                    <i className="fab fa-twitter" />
                                </a>
                                <a href="index.html#" className="single-icon">
                                    <i className="fas fa-rss" />
                                </a>
                                <a href="index.html#" className="single-icon">
                                    <i className="fab fa-youtube" />
                                </a>
                                <a href="index.html#" className="single-icon">
                                    <i className="fab fa-google-plus-g" />
                                </a>
                                <a href="index.html#" className="single-icon">
                                    <i className="fab fa-instagram" />
                                </a>
                            </div>
                        </div> */}
                    </div>
                </aside>
           
        </>
    );
};

export default Header;
