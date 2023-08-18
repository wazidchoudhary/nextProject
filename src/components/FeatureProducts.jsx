import React from "react";
import Image from "next/image";
import Carousel from "react-multi-carousel";
import { useRouter } from "next/router";
import { Card } from "./card";
import "react-multi-carousel/lib/styles.css";
const FeatureProducts = ({ products }) => {
    const responsive = {
        superLargeDesktop: {
            // the naming can be any, depends on you.
            breakpoint: { max: 4000, min: 3000 },
            items: 5,
        },
        desktop: {
            breakpoint: { max: 3000, min: 1024 },
            items: 3,
        },
        tablet: {
            breakpoint: { max: 1024, min: 464 },
            items: 2,
        },
        mobile: {
            breakpoint: { max: 464, min: 0 },
            items: 1,
        },
    };

    const router = useRouter();

    //   const renderProductList = () => {
    //     const content =
    //     return ;
    //   };

    return (
        <section className="section-margin welcome-section">
            <div className="container">
                <div className="row justify-content-center section-padding border-bottom">
                    <div className="col-lg-10">
                        <div className="welcome-content">
                            <div className="section-title">
                                <h2>Featured Products</h2>
                                {/* <Carousel
                                    swipeable={false}
                                    draggable={false}
                                    showDots={true}
                                    responsive={responsive}
                                    ssr={true} // means to render carousel on server-side.
                                    infinite={true}
                                    // autoPlay={this.props.deviceType !== "mobile" ? true : false}
                                    autoPlaySpeed={1000}
                                    keyBoardControl={true}
                                    customTransition="all .5"
                                    transitionDuration={500}
                                    containerClass="carousel-container"
                                    removeArrowOnDeviceType={["tablet", "mobile"]}
                                    deviceType={"desktop"}
                                    dotListClass="custom-dot-list-style"
                                    itemClass="carousel-item-padding-40-px"
                                > */}
                                <div className="shop-product-wrap grid with-pagination row space-db--30">
                                    {products.map((p) => (
                                        <Card key={p.productId}
                                            content={{
                                                category: p.productCategory,
                                                subCategory: p.productSubCategory,
                                                name: p.productName,
                                                priceNew: p.productPrice,
                                                priceOld: p.productPriceOld,
                                                image: p.productImage[0],
                                            }}
                                            handleClick={(id) => {
                                                router.push(`/products/${id}`);
                                            }}
                                        />
                                    ))}
                                    </div>
                                {/* </Carousel> */}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
};

export default FeatureProducts;
