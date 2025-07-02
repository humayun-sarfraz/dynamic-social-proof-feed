(function($){
  'use strict';
  let dspfInterval, dspfFeed = [];
  let $lastPopup;
  const settings = window.DSPF_Ajax || {};
  const showInterval = parseInt(settings.popup_interval) || 6000;
  const hideInterval = parseInt(settings.popup_hide_delay) || 4000;
  const position = settings.popup_position || 'bottom_left';
  const animation = settings.popup_animation || 'slide';
  const showProductViewers = settings.show_product_viewers == 1;
  const showLiveViewers = settings.show_live_viewers == 1;
  const fakeViewersEnabled = settings.fake_viewers_enable == 1;
  let fakeMin = parseInt(settings.fake_viewers_min) || 4;
  let fakeMax = parseInt(settings.fake_viewers_max) || 18;
  let fakeFixed = parseInt(settings.fake_viewers_fixed) || 9;
  let fakeMode = settings.fake_viewers_mode || 'random';

  function getPositionClass(pos) {
    if(pos === 'bottom_right') return 'dspf-bottom_right';
    if(pos === 'top_left') return 'dspf-top_left';
    if(pos === 'top_right') return 'dspf-top_right';
    return 'dspf-bottom_left';
  }
  function animatePopup($el, ani, show) {
    if(ani === 'fade') {
      show ? $el.fadeIn(350) : $el.fadeOut(350);
    } else if(ani === 'bounce') {
      if(show) $el.show().addClass('dspf-ani-bounce');
      else $el.fadeOut(350, function(){ $el.removeClass('dspf-ani-bounce'); });
    } else { // slide (default)
      show ? $el.slideDown(350) : $el.slideUp(350);
    }
  }
  function showPopup(msgHtml) {
    if(!msgHtml) return;
    if($lastPopup) $lastPopup.remove();
    let $popup = $('<div class="dspf-popup" aria-live="polite"></div>').addClass(getPositionClass(position)).hide();
    $popup.html(msgHtml);
    $('body').append($popup);
    animatePopup($popup, animation, true);
    $lastPopup = $popup;
    setTimeout(() => animatePopup($popup, animation, false), hideInterval);
  }
  function nextPopup() {
    if(dspfFeed.length) {
      showPopup(dspfFeed.shift());
    }
  }
  function fetchFeed() {
    $.post(settings.ajax_url, {
      action: 'dspf_fetch_feed',
      nonce: settings.nonce
    }, function(res){
      if(res.success && Array.isArray(res.data)) {
        dspfFeed = res.data.slice(0, parseInt(settings.popup_count) || 10);
      }
    });
  }
  function isVisibleForDevice() {
    let showOnMobile = settings.show_on_mobile == 1;
    let showOnDesktop = settings.show_on_desktop == 1;
    let isMobile = window.matchMedia("(max-width:600px)").matches;
    return (isMobile && showOnMobile) || (!isMobile && showOnDesktop);
  }
  function updateProductViewersBar() {
    if(!showProductViewers) return;
    // Fake mode dynamic
    if(fakeViewersEnabled) {
      let c = fakeMode === 'random' ? Math.floor(Math.random()*(fakeMax-fakeMin+1))+fakeMin : fakeFixed;
      let msg = c === 1
            ? '1 person is viewing this product right now'
            : c+' people are viewing this product right now';
      $('#dspf-product-viewers-bar').text(msg).fadeIn(220);
      return;
    }
    // Live mode dynamic
    if(showLiveViewers && typeof settings.product_id !== 'undefined') {
      $.post(settings.ajax_url, {
        action: 'dspf_product_viewers',
        product_id: settings.product_id,
        nonce: settings.nonce
      }, function(res){
        if(res.success && res.data && typeof res.data.count !== 'undefined' && res.data.count > 0) {
          let msg = res.data.count === 1
              ? '1 person is viewing this product right now'
              : res.data.count+' people are viewing this product right now';
          $('#dspf-product-viewers-bar').text(msg).fadeIn(220);
        } else {
          $('#dspf-product-viewers-bar').hide();
        }
      });
    }
  }
  $(document).ready(function(){
    if(!isVisibleForDevice()) return;
    fetchFeed();
    dspfInterval = setInterval(function(){
      if(dspfFeed.length) nextPopup();
      else fetchFeed();
    }, showInterval);

    if($('#dspf-product-viewers-bar').length && showProductViewers) {
      updateProductViewersBar();
      setInterval(updateProductViewersBar, 5000);
    }
  });
})(jQuery);
