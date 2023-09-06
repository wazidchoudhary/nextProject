import HeroSlider from "@/components/HeroSlider";
import MetaHead from "@/components/MetaHead";
import styles from "./Product.module.css"

export default function() {
    return <>
<MetaHead title="Products" description="best products" />
<section className="bg-image">
                <h2 className="sr-only">Site Breadcrumb</h2>
                <div className="container" style={{ height: "150px" }}></div>
</section>
<section className="section-padding">
  <div className="container">
    <div className="ha-custom-tab">
      
      <div className="tab-content space-db--30" id="myTabContent">
        <div className="tab-pane fade show active" id="shop" role="tabpanel" aria-labelledby="shop-tab">
          <div className="row">
            <div className="col-lg-3 col-sm-6 mb--30">
              <div className="product-card">
                <div className="image">
                  <img src="image/products/home-1/product-1.jpg" alt />
                  <div className="hover-content">
                    <a href="product-details.html" className="hover-image">
                      <img src="image/products/home-1/product-2.jpg" alt />
                    </a>
                    <div className="hover-btns">
                      <a href="cart.html" className="sin-btn"><i className="ion-bag" /></a>
                      <a href="compare.html" className="sin-btn"><i className="ion-android-options" /></a>
                      <a href="javascript:" className="sin-btn" data-bs-toggle="modal" data-bs-target="#quickModal"><i className="ion-android-open" /></a>
                    </div>
                  </div>
                  <span className="product-badge">new</span>
                  <span className="product-badge-2">-5%</span>
                </div>
                <div className="description-block">
                  <div className="description-header">
                    <h5 className="description-tag"><a href>Fashion Manufacturer</a></h5>
                    <div className="rating-block">
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline" />
                      <span className="ion-android-star-outline" />
                    </div>
                  </div>
                  <h3 className="post-title"><a href="product-details.html"> zyfg men sweatshirts
                      casual ethnic
                      style pattern</a></h3>
                  <p className="mb-0 price">
                    <del className="price-old">€500.00</del>
                    <span className="price-new">€500.00</span></p>
                </div>
              </div>
            </div>
            <div className="col-lg-3 col-sm-6 mb--30">
              <div className="product-card">
                <div className="image">
                  <img src="image/products/home-1/product-3.jpg" alt />
                  <div className="hover-content">
                    <a href="product-details.html" className="hover-image">
                      <img src="image/products/home-1/product-4.jpg" alt />
                    </a>
                    <div className="hover-btns">
                      <a href="cart.html" className="sin-btn"><i className="ion-bag" /></a>
                      <a href="compare.html" className="sin-btn"><i className="ion-android-options" /></a>
                      <a href="javascript:" className="sin-btn" data-bs-toggle="modal" data-bs-target="#quickModal"><i className="ion-android-open" /></a>
                    </div>
                  </div>
                  <span className="product-badge">new</span>
                </div>
                <div className="description-block">
                  <div className="description-header">
                    <h5 className="description-tag"><a href>Fashion Manufacturer</a></h5>
                    <div className="rating-block">
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline" />
                      <span className="ion-android-star-outline" />
                    </div>
                  </div>
                  <h3 className="post-title"><a href="product-details.html">yuqidong sudaderas
                      hombre hip hop
                      zipper</a></h3>
                  <p className="mb-0 price">
                    <del className="price-old">€500.00</del>
                    <span className="price-new">€500.00</span></p>
                </div>
              </div>
            </div>
            <div className="col-lg-3 col-sm-6 mb--30">
              <div className="product-card">
                <div className="image">
                  <img src="image/products/home-1/product-5.jpg" alt />
                  <div className="hover-content">
                    <a href="product-details.html" className="hover-image">
                      <img src="image/products/home-1/product-6.jpg" alt />
                    </a>
                    <div className="hover-btns">
                      <a href="cart.html" className="sin-btn"><i className="ion-bag" /></a>
                      <a href="compare.html" className="sin-btn"><i className="ion-android-options" /></a>
                      <a href="javascript:" className="sin-btn" data-bs-toggle="modal" data-bs-target="#quickModal"><i className="ion-android-open" /></a>
                    </div>
                  </div>
                  <span className="product-badge">new</span>
                </div>
                <div className="description-block">
                  <div className="description-header">
                    <h5 className="description-tag"><a href>Inside Autumn Winter</a></h5>
                    <div className="rating-block">
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline" />
                      <span className="ion-android-star-outline" />
                    </div>
                  </div>
                  <h3 className="post-title"><a href="product-details.html">SexeMara Sweatshirts
                      Hoodies Male
                      Tracksuit</a></h3>
                  <p className="mb-0 price">
                    <del className="price-old">€500.00</del>
                    <span className="price-new">€500.00</span></p>
                </div>
              </div>
            </div>
            <div className="col-lg-3 col-sm-6 mb--30">
              <div className="product-card">
                <div className="image">
                  <img src="image/products/home-1/product-7.jpg" alt />
                  <div className="hover-content">
                    <a href="product-details.html" className="hover-image">
                      <img src="image/products/home-1/product-8.jpg" alt />
                    </a>
                    <div className="hover-btns">
                      <a href="cart.html" className="sin-btn"><i className="ion-bag" /></a>
                      <a href="compare.html" className="sin-btn"><i className="ion-android-options" /></a>
                      <a href="javascript:" className="sin-btn" data-bs-toggle="modal" data-bs-target="#quickModal"><i className="ion-android-open" /></a>
                    </div>
                  </div>
                  <span className="product-badge">new</span>
                </div>
                <div className="description-block">
                  <div className="description-header">
                    <h5 className="description-tag"><a href>Fashion Manufacturer</a></h5>
                    <div className="rating-block">
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline" />
                      <span className="ion-android-star-outline" />
                    </div>
                  </div>
                  <h3 className="post-title"><a href="product-details.html"> zengker winter
                      hooded with fat
                      thickening </a></h3>
                  <p className="mb-0 price">
                    <del className="price-old">€500.00</del>
                    <span className="price-new">€500.00</span></p>
                </div>
              </div>
            </div>
            <div className="col-lg-3 col-sm-6 mb--30">
              <div className="product-card">
                <div className="image">
                  <img src="image/products/home-1/product-9.jpg" alt />
                  <div className="hover-content">
                    <a href="product-details.html" className="hover-image">
                      <img src="image/products/home-1/product-10.jpg" alt />
                    </a>
                    <div className="hover-btns">
                      <a href="cart.html" className="sin-btn"><i className="ion-bag" /></a>
                      <a href="compare.html" className="sin-btn"><i className="ion-android-options" /></a>
                      <a href="javascript:" className="sin-btn" data-bs-toggle="modal" data-bs-target="#quickModal"><i className="ion-android-open" /></a>
                    </div>
                  </div>
                  <span className="product-badge">new</span>
                </div>
                <div className="description-block">
                  <div className="description-header">
                    <h5 className="description-tag"><a href>Fashion Manufacturer</a></h5>
                    <div className="rating-block">
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline" />
                      <span className="ion-android-star-outline" />
                    </div>
                  </div>
                  <h3 className="post-title"><a href="product-details.html"> Vantiorango PU
                      Patchwork Knitted
                      Plus</a></h3>
                  <p className="mb-0 price">
                    <del className="price-old">€500.00</del>
                    <span className="price-new">€500.00</span></p>
                </div>
              </div>
            </div>
            <div className="col-lg-3 col-sm-6 mb--30">
              <div className="product-card">
                <div className="image">
                  <img src="image/products/home-1/product-11.jpg" alt />
                  <div className="hover-content">
                    <a href="product-details.html" className="hover-image">
                      <img src="image/products/home-1/product-1.jpg" alt />
                    </a>
                    <div className="hover-btns">
                      <a href="cart.html" className="sin-btn"><i className="ion-bag" /></a>
                      <a href="compare.html" className="sin-btn"><i className="ion-android-options" /></a>
                      <a href="javascript:" className="sin-btn" data-bs-toggle="modal" data-bs-target="#quickModal"><i className="ion-android-open" /></a>
                    </div>
                  </div>
                  <span className="product-badge">new</span>
                </div>
                <div className="description-block">
                  <div className="description-header">
                    <h5 className="description-tag"><a href>Fashion Manufacturer</a></h5>
                    <div className="rating-block">
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline" />
                      <span className="ion-android-star-outline" />
                    </div>
                  </div>
                  <h3 className="post-title"><a href="product-details.html"> Vantiorango PU
                      Patchwork Knitted
                      Plus</a></h3>
                  <p className="mb-0 price">
                    <del className="price-old">€500.00</del>
                    <span className="price-new">€500.00</span></p>
                </div>
              </div>
            </div>
            <div className="col-lg-3 col-sm-6 mb--30">
              <div className="product-card">
                <div className="image">
                  <img src="image/products/home-1/product-13.jpg" alt />
                  <div className="hover-content">
                    <a href="product-details.html" className="hover-image">
                      <img src="image/products/home-1/product-12.jpg" alt />
                    </a>
                    <div className="hover-btns">
                      <a href="cart.html" className="sin-btn"><i className="ion-bag" /></a>
                      <a href="compare.html" className="sin-btn"><i className="ion-android-options" /></a>
                      <a href="javascript:" className="sin-btn" data-bs-toggle="modal" data-bs-target="#quickModal"><i className="ion-android-open" /></a>
                    </div>
                  </div>
                  <span className="product-badge">new</span>
                </div>
                <div className="description-block">
                  <div className="description-header">
                    <h5 className="description-tag"><a href>Fashion Manufacturer</a></h5>
                    <div className="rating-block">
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline" />
                      <span className="ion-android-star-outline" />
                    </div>
                  </div>
                  <h3 className="post-title"><a href="product-details.html"> Vantiorango PU
                      Patchwork Knitted
                      Plus</a></h3>
                  <p className="mb-0 price">
                    <del className="price-old">€500.00</del>
                    <span className="price-new">€500.00</span></p>
                </div>
              </div>
            </div>
            <div className="col-lg-3 col-sm-6 mb--30">
              <div className="product-card">
                <div className="image">
                  <img src="image/products/home-1/product-6.jpg" alt />
                  <div className="hover-content">
                    <a href="product-details.html" className="hover-image">
                      <img src="image/products/home-1/product-5.jpg" alt />
                    </a>
                    <div className="hover-btns">
                      <a href="cart.html" className="sin-btn"><i className="ion-bag" /></a>
                      <a href="compare.html" className="sin-btn"><i className="ion-android-options" /></a>
                      <a href="javascript:" className="sin-btn" data-bs-toggle="modal" data-bs-target="#quickModal"><i className="ion-android-open" /></a>
                    </div>
                  </div>
                  <span className="product-badge">new</span>
                </div>
                <div className="description-block">
                  <div className="description-header">
                    <h5 className="description-tag"><a href>Fashion Manufacturer</a></h5>
                    <div className="rating-block">
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline" />
                      <span className="ion-android-star-outline" />
                    </div>
                  </div>
                  <h3 className="post-title"><a href="product-details.html"> Vantiorango PU
                      Patchwork Knitted
                      Plus</a></h3>
                  <p className="mb-0 price">
                    <del className="price-old">€500.00</del>
                    <span className="price-new">€500.00</span></p>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div className="tab-pane fade" id="men" role="tabpanel" aria-labelledby="men-tab">
          <div className="row">
            <div className="col-lg-3 col-sm-6 mb--30">
              <div className="product-card">
                <div className="image">
                  <img src="image/products/home-1/product-3.jpg" alt />
                  <div className="hover-content">
                    <a href="product-details.html" className="hover-image">
                      <img src="image/products/home-1/product-4.jpg" alt />
                    </a>
                    <div className="hover-btns">
                      <a href="cart.html" className="sin-btn"><i className="ion-bag" /></a>
                      <a href="compare.html" className="sin-btn"><i className="ion-android-options" /></a>
                      <a href="javascript:" className="sin-btn" data-bs-toggle="modal" data-bs-target="#quickModal"><i className="ion-android-open" /></a>
                    </div>
                  </div>
                  <span className="product-badge">new</span>
                </div>
                <div className="description-block">
                  <div className="description-header">
                    <h5 className="description-tag"><a href>Fashion Manufacturer</a></h5>
                    <div className="rating-block">
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline" />
                      <span className="ion-android-star-outline" />
                    </div>
                  </div>
                  <h3 className="post-title"><a href="product-details.html">yuqidong sudaderas
                      hombre hip hop
                      zipper</a></h3>
                  <p className="mb-0 price">
                    <del className="price-old">€500.00</del>
                    <span className="price-new">€500.00</span></p>
                </div>
              </div>
            </div>
            <div className="col-lg-3 col-sm-6 mb--30">
              <div className="product-card">
                <div className="image">
                  <img src="image/products/home-1/product-1.jpg" alt />
                  <div className="hover-content">
                    <a href="product-details.html" className="hover-image">
                      <img src="image/products/home-1/product-2.jpg" alt />
                    </a>
                    <div className="hover-btns">
                      <a href="cart.html" className="sin-btn"><i className="ion-bag" /></a>
                      <a href="compare.html" className="sin-btn"><i className="ion-android-options" /></a>
                      <a href="javascript:" className="sin-btn" data-bs-toggle="modal" data-bs-target="#quickModal"><i className="ion-android-open" /></a>
                    </div>
                  </div>
                  <span className="product-badge">new</span>
                </div>
                <div className="description-block">
                  <div className="description-header">
                    <h5 className="description-tag"><a href>Fashion Manufacturer</a></h5>
                    <div className="rating-block">
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline" />
                      <span className="ion-android-star-outline" />
                    </div>
                  </div>
                  <h3 className="post-title"><a href="product-details.html"> zyfg men sweatshirts
                      casual ethnic
                      style pattern</a></h3>
                  <p className="mb-0 price">
                    <del className="price-old">€500.00</del>
                    <span className="price-new">€500.00</span></p>
                </div>
              </div>
            </div>
            <div className="col-lg-3 col-sm-6 mb--30">
              <div className="product-card">
                <div className="image">
                  <img src="image/products/home-1/product-7.jpg" alt />
                  <div className="hover-content">
                    <a href="product-details.html" className="hover-image">
                      <img src="image/products/home-1/product-8.jpg" alt />
                    </a>
                    <div className="hover-btns">
                      <a href="cart.html" className="sin-btn"><i className="ion-bag" /></a>
                      <a href="compare.html" className="sin-btn"><i className="ion-android-options" /></a>
                      <a href="javascript:" className="sin-btn" data-bs-toggle="modal" data-bs-target="#quickModal"><i className="ion-android-open" /></a>
                    </div>
                  </div>
                  <span className="product-badge">new</span>
                </div>
                <div className="description-block">
                  <div className="description-header">
                    <h5 className="description-tag"><a href>Fashion Manufacturer</a></h5>
                    <div className="rating-block">
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline" />
                      <span className="ion-android-star-outline" />
                    </div>
                  </div>
                  <h3 className="post-title"><a href="product-details.html"> zengker winter
                      hooded with fat
                      thickening </a></h3>
                  <p className="mb-0 price">
                    <del className="price-old">€500.00</del>
                    <span className="price-new">€500.00</span></p>
                </div>
              </div>
            </div>
            <div className="col-lg-3 col-sm-6 mb--30">
              <div className="product-card">
                <div className="image">
                  <img src="image/products/home-1/product-5.jpg" alt />
                  <div className="hover-content">
                    <a href="product-details.html" className="hover-image">
                      <img src="image/products/home-1/product-6.jpg" alt />
                    </a>
                    <div className="hover-btns">
                      <a href="cart.html" className="sin-btn"><i className="ion-bag" /></a>
                      <a href="compare.html" className="sin-btn"><i className="ion-android-options" /></a>
                      <a href="javascript:" className="sin-btn" data-bs-toggle="modal" data-bs-target="#quickModal"><i className="ion-android-open" /></a>
                    </div>
                  </div>
                  <span className="product-badge">new</span>
                </div>
                <div className="description-block">
                  <div className="description-header">
                    <h5 className="description-tag"><a href>Inside Autumn Winter</a></h5>
                    <div className="rating-block">
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline" />
                      <span className="ion-android-star-outline" />
                    </div>
                  </div>
                  <h3 className="post-title"><a href="product-details.html">SexeMara Sweatshirts
                      Hoodies Male
                      Tracksuit</a></h3>
                  <p className="mb-0 price">
                    <del className="price-old">€500.00</del>
                    <span className="price-new">€500.00</span></p>
                </div>
              </div>
            </div>
            <div className="col-lg-3 col-sm-6 mb--30">
              <div className="product-card">
                <div className="image">
                  <img src="image/products/home-1/product-11.jpg" alt />
                  <div className="hover-content">
                    <a href="product-details.html" className="hover-image">
                      <img src="image/products/home-1/product-1.jpg" alt />
                    </a>
                    <div className="hover-btns">
                      <a href="cart.html" className="sin-btn"><i className="ion-bag" /></a>
                      <a href="compare.html" className="sin-btn"><i className="ion-android-options" /></a>
                      <a href="javascript:" className="sin-btn" data-bs-toggle="modal" data-bs-target="#quickModal"><i className="ion-android-open" /></a>
                    </div>
                  </div>
                  <span className="product-badge">new</span>
                </div>
                <div className="description-block">
                  <div className="description-header">
                    <h5 className="description-tag"><a href>Fashion Manufacturer</a></h5>
                    <div className="rating-block">
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline" />
                      <span className="ion-android-star-outline" />
                    </div>
                  </div>
                  <h3 className="post-title"><a href="product-details.html"> Vantiorango PU
                      Patchwork Knitted
                      Plus</a></h3>
                  <p className="mb-0 price">
                    <del className="price-old">€500.00</del>
                    <span className="price-new">€500.00</span></p>
                </div>
              </div>
            </div>
            <div className="col-lg-3 col-sm-6 mb--30">
              <div className="product-card">
                <div className="image">
                  <img src="image/products/home-1/product-9.jpg" alt />
                  <div className="hover-content">
                    <a href="product-details.html" className="hover-image">
                      <img src="image/products/home-1/product-10.jpg" alt />
                    </a>
                    <div className="hover-btns">
                      <a href="cart.html" className="sin-btn"><i className="ion-bag" /></a>
                      <a href="compare.html" className="sin-btn"><i className="ion-android-options" /></a>
                      <a href="javascript:" className="sin-btn" data-bs-toggle="modal" data-bs-target="#quickModal"><i className="ion-android-open" /></a>
                    </div>
                  </div>
                  <span className="product-badge">new</span>
                </div>
                <div className="description-block">
                  <div className="description-header">
                    <h5 className="description-tag"><a href>Fashion Manufacturer</a></h5>
                    <div className="rating-block">
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline" />
                      <span className="ion-android-star-outline" />
                    </div>
                  </div>
                  <h3 className="post-title"><a href="product-details.html"> Vantiorango PU
                      Patchwork Knitted
                      Plus</a></h3>
                  <p className="mb-0 price">
                    <del className="price-old">€500.00</del>
                    <span className="price-new">€500.00</span></p>
                </div>
              </div>
            </div>
            <div className="col-lg-3 col-sm-6 mb--30">
              <div className="product-card">
                <div className="image">
                  <img src="image/products/home-1/product-6.jpg" alt />
                  <div className="hover-content">
                    <a href="product-details.html" className="hover-image">
                      <img src="image/products/home-1/product-5.jpg" alt />
                    </a>
                    <div className="hover-btns">
                      <a href="cart.html" className="sin-btn"><i className="ion-bag" /></a>
                      <a href="compare.html" className="sin-btn"><i className="ion-android-options" /></a>
                      <a href="javascript:" className="sin-btn" data-bs-toggle="modal" data-bs-target="#quickModal"><i className="ion-android-open" /></a>
                    </div>
                  </div>
                  <span className="product-badge">new</span>
                </div>
                <div className="description-block">
                  <div className="description-header">
                    <h5 className="description-tag"><a href>Fashion Manufacturer</a></h5>
                    <div className="rating-block">
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline" />
                      <span className="ion-android-star-outline" />
                    </div>
                  </div>
                  <h3 className="post-title"><a href="product-details.html"> Vantiorango PU
                      Patchwork Knitted
                      Plus</a></h3>
                  <p className="mb-0 price">
                    <del className="price-old">€500.00</del>
                    <span className="price-new">€500.00</span></p>
                </div>
              </div>
            </div>
            <div className="col-lg-3 col-sm-6 mb--30">
              <div className="product-card">
                <div className="image">
                  <img src="image/products/home-1/product-13.jpg" alt />
                  <div className="hover-content">
                    <a href="product-details.html" className="hover-image">
                      <img src="image/products/home-1/product-12.jpg" alt />
                    </a>
                    <div className="hover-btns">
                      <a href="cart.html" className="sin-btn"><i className="ion-bag" /></a>
                      <a href="compare.html" className="sin-btn"><i className="ion-android-options" /></a>
                      <a href="javascript:" className="sin-btn" data-bs-toggle="modal" data-bs-target="#quickModal"><i className="ion-android-open" /></a>
                    </div>
                  </div>
                  <span className="product-badge">new</span>
                </div>
                <div className="description-block">
                  <div className="description-header">
                    <h5 className="description-tag"><a href>Fashion Manufacturer</a></h5>
                    <div className="rating-block">
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline" />
                      <span className="ion-android-star-outline" />
                    </div>
                  </div>
                  <h3 className="post-title"><a href="product-details.html"> Vantiorango PU
                      Patchwork Knitted
                      Plus</a></h3>
                  <p className="mb-0 price">
                    <del className="price-old">€500.00</del>
                    <span className="price-new">€500.00</span></p>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div className="tab-pane fade" id="woman" role="tabpanel" aria-labelledby="woman-tab">
          <div className="row">
            <div className="col-lg-3 col-sm-6 mb--30">
              <div className="product-card">
                <div className="image">
                  <img src="image/products/home-1/product-1.jpg" alt />
                  <div className="hover-content">
                    <a href="product-details.html" className="hover-image">
                      <img src="image/products/home-1/product-2.jpg" alt />
                    </a>
                    <div className="hover-btns">
                      <a href="cart.html" className="sin-btn"><i className="ion-bag" /></a>
                      <a href="compare.html" className="sin-btn"><i className="ion-android-options" /></a>
                      <a href="javascript:" className="sin-btn" data-bs-toggle="modal" data-bs-target="#quickModal"><i className="ion-android-open" /></a>
                    </div>
                  </div>
                  <span className="product-badge">new</span>
                </div>
                <div className="description-block">
                  <div className="description-header">
                    <h5 className="description-tag"><a href>Fashion Manufacturer</a></h5>
                    <div className="rating-block">
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline" />
                      <span className="ion-android-star-outline" />
                    </div>
                  </div>
                  <h3 className="post-title"><a href="product-details.html"> zyfg men sweatshirts
                      casual ethnic
                      style pattern</a></h3>
                  <p className="mb-0 price">
                    <del className="price-old">€500.00</del>
                    <span className="price-new">€500.00</span></p>
                </div>
              </div>
            </div>
            <div className="col-lg-3 col-sm-6 mb--30">
              <div className="product-card">
                <div className="image">
                  <img src="image/products/home-1/product-3.jpg" alt />
                  <div className="hover-content">
                    <a href="product-details.html" className="hover-image">
                      <img src="image/products/home-1/product-4.jpg" alt />
                    </a>
                    <div className="hover-btns">
                      <a href="cart.html" className="sin-btn"><i className="ion-bag" /></a>
                      <a href="compare.html" className="sin-btn"><i className="ion-android-options" /></a>
                      <a href="javascript:" className="sin-btn" data-bs-toggle="modal" data-bs-target="#quickModal"><i className="ion-android-open" /></a>
                    </div>
                  </div>
                  <span className="product-badge">new</span>
                </div>
                <div className="description-block">
                  <div className="description-header">
                    <h5 className="description-tag"><a href>Fashion Manufacturer</a></h5>
                    <div className="rating-block">
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline" />
                      <span className="ion-android-star-outline" />
                    </div>
                  </div>
                  <h3 className="post-title"><a href="product-details.html">yuqidong sudaderas
                      hombre hip hop
                      zipper</a></h3>
                  <p className="mb-0 price">
                    <del className="price-old">€500.00</del>
                    <span className="price-new">€500.00</span></p>
                </div>
              </div>
            </div>
            <div className="col-lg-3 col-sm-6 mb--30">
              <div className="product-card">
                <div className="image">
                  <img src="image/products/home-1/product-9.jpg" alt />
                  <div className="hover-content">
                    <a href="product-details.html" className="hover-image">
                      <img src="image/products/home-1/product-10.jpg" alt />
                    </a>
                    <div className="hover-btns">
                      <a href="cart.html" className="sin-btn"><i className="ion-bag" /></a>
                      <a href="compare.html" className="sin-btn"><i className="ion-android-options" /></a>
                      <a href="javascript:" className="sin-btn" data-bs-toggle="modal" data-bs-target="#quickModal"><i className="ion-android-open" /></a>
                    </div>
                  </div>
                </div>
                <div className="description-block">
                  <div className="description-header">
                    <h5 className="description-tag"><a href>Fashion Manufacturer</a></h5>
                    <div className="rating-block">
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline" />
                      <span className="ion-android-star-outline" />
                    </div>
                  </div>
                  <h3 className="post-title"><a href="product-details.html"> Vantiorango PU
                      Patchwork Knitted
                      Plus</a></h3>
                  <p className="mb-0 price">
                    <del className="price-old">€500.00</del>
                    <span className="price-new">€500.00</span></p>
                </div>
              </div>
            </div>
            <div className="col-lg-3 col-sm-6 mb--30">
              <div className="product-card">
                <div className="image">
                  <img src="image/products/home-1/product-5.jpg" alt />
                  <div className="hover-content">
                    <a href="product-details.html" className="hover-image">
                      <img src="image/products/home-1/product-6.jpg" alt />
                    </a>
                    <div className="hover-btns">
                      <a href="cart.html" className="sin-btn"><i className="ion-bag" /></a>
                      <a href="compare.html" className="sin-btn"><i className="ion-android-options" /></a>
                      <a href="javascript:" className="sin-btn" data-bs-toggle="modal" data-bs-target="#quickModal"><i className="ion-android-open" /></a>
                    </div>
                  </div>
                </div>
                <div className="description-block">
                  <div className="description-header">
                    <h5 className="description-tag"><a href>Inside Autumn Winter</a></h5>
                    <div className="rating-block">
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline" />
                      <span className="ion-android-star-outline" />
                    </div>
                  </div>
                  <h3 className="post-title"><a href="product-details.html">SexeMara Sweatshirts
                      Hoodies Male
                      Tracksuit</a></h3>
                  <p className="mb-0 price">
                    <del className="price-old">€500.00</del>
                    <span className="price-new">€500.00</span></p>
                </div>
              </div>
            </div>
            <div className="col-lg-3 col-sm-6 mb--30">
              <div className="product-card">
                <div className="image">
                  <img src="image/products/home-1/product-7.jpg" alt />
                  <div className="hover-content">
                    <a href="product-details.html" className="hover-image">
                      <img src="image/products/home-1/product-8.jpg" alt />
                    </a>
                    <div className="hover-btns">
                      <a href="cart.html" className="sin-btn"><i className="ion-bag" /></a>
                      <a href="compare.html" className="sin-btn"><i className="ion-android-options" /></a>
                      <a href="javascript:" className="sin-btn" data-bs-toggle="modal" data-bs-target="#quickModal"><i className="ion-android-open" /></a>
                    </div>
                  </div>
                </div>
                <div className="description-block">
                  <div className="description-header">
                    <h5 className="description-tag"><a href>Fashion Manufacturer</a></h5>
                    <div className="rating-block">
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline" />
                      <span className="ion-android-star-outline" />
                    </div>
                  </div>
                  <h3 className="post-title"><a href="product-details.html"> zengker winter
                      hooded with fat
                      thickening </a></h3>
                  <p className="mb-0 price">
                    <del className="price-old">€500.00</del>
                    <span className="price-new">€500.00</span></p>
                </div>
              </div>
            </div>
            <div className="col-lg-3 col-sm-6 mb--30">
              <div className="product-card">
                <div className="image">
                  <img src="image/products/home-1/product-6.jpg" alt />
                  <div className="hover-content">
                    <a href="product-details.html" className="hover-image">
                      <img src="image/products/home-1/product-5.jpg" alt />
                    </a>
                    <div className="hover-btns">
                      <a href="cart.html" className="sin-btn"><i className="ion-bag" /></a>
                      <a href="compare.html" className="sin-btn"><i className="ion-android-options" /></a>
                      <a href="javascript:" className="sin-btn" data-bs-toggle="modal" data-bs-target="#quickModal"><i className="ion-android-open" /></a>
                    </div>
                  </div>
                </div>
                <div className="description-block">
                  <div className="description-header">
                    <h5 className="description-tag"><a href>Fashion Manufacturer</a></h5>
                    <div className="rating-block">
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline" />
                      <span className="ion-android-star-outline" />
                    </div>
                  </div>
                  <h3 className="post-title"><a href="product-details.html"> Vantiorango PU
                      Patchwork Knitted
                      Plus</a></h3>
                  <p className="mb-0 price">
                    <del className="price-old">€500.00</del>
                    <span className="price-new">€500.00</span></p>
                </div>
              </div>
            </div>
            <div className="col-lg-3 col-sm-6 mb--30">
              <div className="product-card">
                <div className="image">
                  <img src="image/products/home-1/product-11.jpg" alt />
                  <div className="hover-content">
                    <a href="product-details.html" className="hover-image">
                      <img src="image/products/home-1/product-1.jpg" alt />
                    </a>
                    <div className="hover-btns">
                      <a href="cart.html" className="sin-btn"><i className="ion-bag" /></a>
                      <a href="compare.html" className="sin-btn"><i className="ion-android-options" /></a>
                      <a href="javascript:" className="sin-btn" data-bs-toggle="modal" data-bs-target="#quickModal"><i className="ion-android-open" /></a>
                    </div>
                  </div>
                </div>
                <div className="description-block">
                  <div className="description-header">
                    <h5 className="description-tag"><a href>Fashion Manufacturer</a></h5>
                    <div className="rating-block">
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline" />
                      <span className="ion-android-star-outline" />
                    </div>
                  </div>
                  <h3 className="post-title"><a href="product-details.html"> Vantiorango PU
                      Patchwork Knitted
                      Plus</a></h3>
                  <p className="mb-0 price">
                    <del className="price-old">€500.00</del>
                    <span className="price-new">€500.00</span></p>
                </div>
              </div>
            </div>
            <div className="col-lg-3 col-sm-6 mb--30">
              <div className="product-card">
                <div className="image">
                  <img src="image/products/home-1/product-13.jpg" alt />
                  <div className="hover-content">
                    <a href="product-details.html" className="hover-image">
                      <img src="image/products/home-1/product-12.jpg" alt />
                    </a>
                    <div className="hover-btns">
                      <a href="cart.html" className="sin-btn"><i className="ion-bag" /></a>
                      <a href="compare.html" className="sin-btn"><i className="ion-android-options" /></a>
                      <a href="javascript:" className="sin-btn" data-bs-toggle="modal" data-bs-target="#quickModal"><i className="ion-android-open" /></a>
                    </div>
                  </div>
                </div>
                <div className="description-block">
                  <div className="description-header">
                    <h5 className="description-tag"><a href>Fashion Manufacturer</a></h5>
                    <div className="rating-block">
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline" />
                      <span className="ion-android-star-outline" />
                    </div>
                  </div>
                  <h3 className="post-title"><a href="product-details.html"> Vantiorango PU
                      Patchwork Knitted
                      Plus</a></h3>
                  <p className="mb-0 price">
                    <del className="price-old">€500.00</del>
                    <span className="price-new">€500.00</span></p>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div className="tab-pane fade" id="elements" role="tabpanel" aria-labelledby="elements-tab">
          <div className="row">
            <div className="col-lg-3 col-sm-6 mb--30">
              <div className="product-card">
                <div className="image">
                  <img src="image/products/home-1/product-1.jpg" alt />
                  <div className="hover-content">
                    <a href="product-details.html" className="hover-image">
                      <img src="image/products/home-1/product-2.jpg" alt />
                    </a>
                    <div className="hover-btns">
                      <a href="cart.html" className="sin-btn"><i className="ion-bag" /></a>
                      <a href="compare.html" className="sin-btn"><i className="ion-android-options" /></a>
                      <a href="javascript:" className="sin-btn" data-bs-toggle="modal" data-bs-target="#quickModal"><i className="ion-android-open" /></a>
                    </div>
                  </div>
                </div>
                <div className="description-block">
                  <div className="description-header">
                    <h5 className="description-tag"><a href>Fashion Manufacturer</a></h5>
                    <div className="rating-block">
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline" />
                      <span className="ion-android-star-outline" />
                    </div>
                  </div>
                  <h3 className="post-title"><a href="product-details.html"> zyfg men sweatshirts
                      casual ethnic
                      style pattern</a></h3>
                  <p className="mb-0 price">
                    <del className="price-old">€500.00</del>
                    <span className="price-new">€500.00</span></p>
                </div>
              </div>
            </div>
            <div className="col-lg-3 col-sm-6 mb--30">
              <div className="product-card">
                <div className="image">
                  <img src="image/products/home-1/product-5.jpg" alt />
                  <div className="hover-content">
                    <a href="product-details.html" className="hover-image">
                      <img src="image/products/home-1/product-6.jpg" alt />
                    </a>
                    <div className="hover-btns">
                      <a href="cart.html" className="sin-btn"><i className="ion-bag" /></a>
                      <a href="compare.html" className="sin-btn"><i className="ion-android-options" /></a>
                      <a href="javascript:" className="sin-btn" data-bs-toggle="modal" data-bs-target="#quickModal"><i className="ion-android-open" /></a>
                    </div>
                  </div>
                </div>
                <div className="description-block">
                  <div className="description-header">
                    <h5 className="description-tag"><a href>Inside Autumn Winter</a></h5>
                    <div className="rating-block">
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline" />
                      <span className="ion-android-star-outline" />
                    </div>
                  </div>
                  <h3 className="post-title"><a href="product-details.html">SexeMara Sweatshirts
                      Hoodies Male
                      Tracksuit</a></h3>
                  <p className="mb-0 price">
                    <del className="price-old">€500.00</del>
                    <span className="price-new">€500.00</span></p>
                </div>
              </div>
            </div>
            <div className="col-lg-3 col-sm-6 mb--30">
              <div className="product-card">
                <div className="image">
                  <img src="image/products/home-1/product-6.jpg" alt />
                  <div className="hover-content">
                    <a href="product-details.html" className="hover-image">
                      <img src="image/products/home-1/product-5.jpg" alt />
                    </a>
                    <div className="hover-btns">
                      <a href="cart.html" className="sin-btn"><i className="ion-bag" /></a>
                      <a href="compare.html" className="sin-btn"><i className="ion-android-options" /></a>
                      <a href="javascript:" className="sin-btn" data-bs-toggle="modal" data-bs-target="#quickModal"><i className="ion-android-open" /></a>
                    </div>
                  </div>
                </div>
                <div className="description-block">
                  <div className="description-header">
                    <h5 className="description-tag"><a href>Fashion Manufacturer</a></h5>
                    <div className="rating-block">
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline" />
                      <span className="ion-android-star-outline" />
                    </div>
                  </div>
                  <h3 className="post-title"><a href="product-details.html"> Vantiorango PU
                      Patchwork Knitted
                      Plus</a></h3>
                  <p className="mb-0 price">
                    <del className="price-old">€500.00</del>
                    <span className="price-new">€500.00</span></p>
                </div>
              </div>
            </div>
            <div className="col-lg-3 col-sm-6 mb--30">
              <div className="product-card">
                <div className="image">
                  <img src="image/products/home-1/product-7.jpg" alt />
                  <div className="hover-content">
                    <a href="product-details.html" className="hover-image">
                      <img src="image/products/home-1/product-8.jpg" alt />
                    </a>
                    <div className="hover-btns">
                      <a href="cart.html" className="sin-btn"><i className="ion-bag" /></a>
                      <a href="compare.html" className="sin-btn"><i className="ion-android-options" /></a>
                      <a href="javascript:" className="sin-btn" data-bs-toggle="modal" data-bs-target="#quickModal"><i className="ion-android-open" /></a>
                    </div>
                  </div>
                </div>
                <div className="description-block">
                  <div className="description-header">
                    <h5 className="description-tag"><a href>Fashion Manufacturer</a></h5>
                    <div className="rating-block">
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline" />
                      <span className="ion-android-star-outline" />
                    </div>
                  </div>
                  <h3 className="post-title"><a href="product-details.html"> zengker winter
                      hooded with fat
                      thickening </a></h3>
                  <p className="mb-0 price">
                    <del className="price-old">€500.00</del>
                    <span className="price-new">€500.00</span></p>
                </div>
              </div>
            </div>
            <div className="col-lg-3 col-sm-6 mb--30">
              <div className="product-card">
                <div className="image">
                  <img src="image/products/home-1/product-9.jpg" alt />
                  <div className="hover-content">
                    <a href="product-details.html" className="hover-image">
                      <img src="image/products/home-1/product-10.jpg" alt />
                    </a>
                    <div className="hover-btns">
                      <a href="cart.html" className="sin-btn"><i className="ion-bag" /></a>
                      <a href="compare.html" className="sin-btn"><i className="ion-android-options" /></a>
                      <a href="javascript:" className="sin-btn" data-bs-toggle="modal" data-bs-target="#quickModal"><i className="ion-android-open" /></a>
                    </div>
                  </div>
                </div>
                <div className="description-block">
                  <div className="description-header">
                    <h5 className="description-tag"><a href>Fashion Manufacturer</a></h5>
                    <div className="rating-block">
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline" />
                      <span className="ion-android-star-outline" />
                    </div>
                  </div>
                  <h3 className="post-title"><a href="product-details.html"> Vantiorango PU
                      Patchwork Knitted
                      Plus</a></h3>
                  <p className="mb-0 price">
                    <del className="price-old">€500.00</del>
                    <span className="price-new">€500.00</span></p>
                </div>
              </div>
            </div>
            <div className="col-lg-3 col-sm-6 mb--30">
              <div className="product-card">
                <div className="image">
                  <img src="image/products/home-1/product-11.jpg" alt />
                  <div className="hover-content">
                    <a href="product-details.html" className="hover-image">
                      <img src="image/products/home-1/product-1.jpg" alt />
                    </a>
                    <div className="hover-btns">
                      <a href="cart.html" className="sin-btn"><i className="ion-bag" /></a>
                      <a href="compare.html" className="sin-btn"><i className="ion-android-options" /></a>
                      <a href="javascript:" className="sin-btn" data-bs-toggle="modal" data-bs-target="#quickModal"><i className="ion-android-open" /></a>
                    </div>
                  </div>
                </div>
                <div className="description-block">
                  <div className="description-header">
                    <h5 className="description-tag"><a href>Fashion Manufacturer</a></h5>
                    <div className="rating-block">
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline" />
                      <span className="ion-android-star-outline" />
                    </div>
                  </div>
                  <h3 className="post-title"><a href="product-details.html"> Vantiorango PU
                      Patchwork Knitted
                      Plus</a></h3>
                  <p className="mb-0 price">
                    <del className="price-old">€500.00</del>
                    <span className="price-new">€500.00</span></p>
                </div>
              </div>
            </div>
            <div className="col-lg-3 col-sm-6 mb--30">
              <div className="product-card">
                <div className="image">
                  <img src="image/products/home-1/product-13.jpg" alt />
                  <div className="hover-content">
                    <a href="product-details.html" className="hover-image">
                      <img src="image/products/home-1/product-12.jpg" alt />
                    </a>
                    <div className="hover-btns">
                      <a href="cart.html" className="sin-btn"><i className="ion-bag" /></a>
                      <a href="compare.html" className="sin-btn"><i className="ion-android-options" /></a>
                      <a href="javascript:" className="sin-btn" data-bs-toggle="modal" data-bs-target="#quickModal"><i className="ion-android-open" /></a>
                    </div>
                  </div>
                </div>
                <div className="description-block">
                  <div className="description-header">
                    <h5 className="description-tag"><a href>Fashion Manufacturer</a></h5>
                    <div className="rating-block">
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline" />
                      <span className="ion-android-star-outline" />
                    </div>
                  </div>
                  <h3 className="post-title"><a href="product-details.html"> Vantiorango PU
                      Patchwork Knitted
                      Plus</a></h3>
                  <p className="mb-0 price">
                    <del className="price-old">€500.00</del>
                    <span className="price-new">€500.00</span></p>
                </div>
              </div>
            </div>
            <div className="col-lg-3 col-sm-6 mb--30">
              <div className="product-card">
                <div className="image">
                  <img src="image/products/home-1/product-3.jpg" alt />
                  <div className="hover-content">
                    <a href="product-details.html" className="hover-image">
                      <img src="image/products/home-1/product-4.jpg" alt />
                    </a>
                    <div className="hover-btns">
                      <a href="cart.html" className="sin-btn"><i className="ion-bag" /></a>
                      <a href="compare.html" className="sin-btn"><i className="ion-android-options" /></a>
                      <a href="javascript:" className="sin-btn" data-bs-toggle="modal" data-bs-target="#quickModal"><i className="ion-android-open" /></a>
                    </div>
                  </div>
                </div>
                <div className="description-block">
                  <div className="description-header">
                    <h5 className="description-tag"><a href>Fashion Manufacturer</a></h5>
                    <div className="rating-block">
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline star_on" />
                      <span className="ion-android-star-outline" />
                      <span className="ion-android-star-outline" />
                    </div>
                  </div>
                  <h3 className="post-title"><a href="product-details.html">yuqidong sudaderas
                      hombre hip hop
                      zipper</a></h3>
                  <p className="mb-0 price">
                    <del className="price-old">€500.00</del>
                    <span className="price-new">€500.00</span></p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

    </>
}