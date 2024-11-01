jQuery(document).ready(function($) {
var pressed = false;

function textarea_changed($elem) {
	pressed = false;

	if(!pressed) {
		$elem.trigger('change');
	}
}

var sms_max_parts = 1;
var sms_footer = '';

sms_content = function() {
	this.unicode;
	this.content_length;
  this.content_length_approximate;
	this.special_chars_length;
}

sms_counter_new = function( $textarea ) {

	this.$textarea = $textarea;

	this.$normalize;
	this.$flash;
	this.$gsm;
	this.$left;
	this.$count;
	this.special_hint;

/*    if(normalize_chars) {
        this.normalize_regexp = new RegExp('[' + normalize_chars + ']', 'g');
    }*/

}

var sms_counter_average_length = [];

/**
 *	unicodes
 *	@todo na stałe
 *
 *	0 - no unicode
 *	1 - unicode
 *	2 - gsm
 *	3 - bin - do dorobienia w przysłości
 */
sms_counter_new.prototype = {

	recount: function( ) {

		if( this.get_normalize() ) {
			this.$gsm
				.attr( 'checked', false )
				.attr( 'disabled', true );
		}

		var footer_content = this.sms_content( sms_footer );

		var content = this.$textarea.val();
		var force_unicode = (footer_content.unicode == 1);

		var sms_content = this.sms_content( content, force_unicode );

		var max_chars = this.get_max_chars( sms_content.unicode );

		var full_content_length = footer_content.content_length + sms_content.content_length;

        var asd = sms_content.content_length_approximate ? "~" : "";
		var left = Math.max(max_chars - full_content_length, 0);

		var parts = Math.min(this.get_parts_count( full_content_length, sms_content.unicode ), sms_max_parts);

		this.$left.html(asd + left);
		this.$count.html(parts);

        var left_has_red = this.$left.hasClass('red');

		if( sms_content.content_length > 0  ) {
			!left_has_red && this.$left.addClass('red');
		} else {
			left_has_red && this.$left.removeClass('red');
		}

		parts_has_red = this.$count.hasClass('red');

		if(parts > 1) {
			!parts_has_red && this.$count.addClass('red')
		} else {
			parts_has_red && this.$count.removeClass('red')
		}

		if(sms_content.unicode == 1) {
			this.$flash
				.attr('disabled', true)
				.attr('checked', false);

			this.$gsm
				.attr('disabled', true)
				.attr('checked', false);

			this.$special_hint.show();
		}else {
			this.$flash.attr('disabled', false);
			this.$special_hint.hide();
		}

		if(!force_unicode && !this.get_normalize() && this.is_valid_gsm( content )) {
			this.$gsm.attr('disabled', false)
		}

		if( left == 0 ) {
			cut_length = max_chars - footer_content.content_length
									- footer_content.special_chars_length
									- sms_content.special_chars_length;

			this.$textarea.val(content.substr(0, cut_length));
		}

	},

	sms_content: function ( content, force_unicode ) {

		var unicode = 1;

		_sms_content = new sms_content();

		if( !force_unicode ) {
			if( this.get_normalize() ) {
				content = this.normalize( content );
			}

			if ( this.get_gsm() && this.is_valid_gsm( content ) ) {
				unicode = 2;
			} else if ( this.is_valid_no_unicode( content ) ) {
				unicode = 0;
			}
		}

		_sms_content.unicode = unicode;
		_sms_content.content_length = this.get_text_length(content, unicode);
        _sms_content.content_length_approximate = (content.match(/(\[%.*?%\])/) != null);
		_sms_content.special_chars_length = _sms_content.content_length - content.length;

		return _sms_content;
	},

	get_max_chars: function( unicode ) {
		var max_chars;

		if(sms_max_parts == 1) {
			max_chars = (unicode == 1) ? 70 : 160;
		}else {
			max_chars = ( (unicode == 1) ? 67 : 153) * sms_max_parts;
		}

		return max_chars;
	},

	get_parts_count: function( sms_length, unicode ) {

		if( unicode == 1 ) {
			return sms_length <= 70 ? 1 : Math.ceil( sms_length / 67 );
		}

		return sms_length <= 160 ? 1 : Math.ceil( sms_length / 153 );
	},

	get_normalize: function() {
		return this.$normalize.attr( 'checked' );
	},

	normalize: function( content ) {

        var text = content;
        var chars = [];

        for (var key in normalize_chars_map) {
            if (!normalize_chars_map.hasOwnProperty(key)) {
                continue;
            }
            chars.push(normalize_chars_map[key]);
            text = text.replace(new RegExp('['+ key +']', 'g'), normalize_chars_map[key]);
        }

        text = text.replace(/[^\@\£\$\¥\è\é\ù\ì\ò\Ç\Ø\ø\Å\å\Δ\_\Φ\Γ\Λ\Ω\Π\Ψ\Σ\Θ\Ξ\^\{\}\\[\~\]\|\Æ\æ\ß\É\!\\\"\#\¤\%\&\\\'\(\)\*\+\,\-\.\/0123456789:;<=>?\¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà€ \r\n]/g, '')

        return text;
	},

	get_gsm: function() {
		return this.$gsm.attr('checked');
	},

	is_valid_gsm: function ( content ) {
		return !/[^A-Z0-9_@£\$¥èéùìòÇØø\+%&\!"#'\(\)\*,\-\.ÅåÆæß¤:;<=>?¡ÄÖÑÜ§¿äöñüà€ΓΔΘΛΞΠΣΦΨΩαβγδεζηθικλμνξοπρστυφχψωςέάόίώύήϊϋΐΰΆΈΊΉΌΎΏXΥΡΟΝΜΚΗΖΕΙΤΑΧΒ\^\{\}\\\[~\]\|\/ \n\r]/ig.test(content);
	},

	is_valid_no_unicode: function( content ) {
		return !/[^\@\£\$\¥\è\é\ù\ì\ò\Ç\Ø\ø\Å\å\Δ\_\Φ\Γ\Λ\Ω\Π\Ψ\Σ\Θ\Ξ\^\{\}\\[\~\]\|\Æ\æ\ß\É\!\\\"\#\¤\%\&\\\'\(\)\*\+\,\-\.\/0123456789:;<=>?\¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà€ \r\n]/.test(content);
	},

	get_text_length: function( content, unicode ) {

		var _content = content.replace(/(\n\r|\r\n)/, '\n');

        for(l in sms_counter_average_length) {

            r = sms_counter_average_length[l];

            var replacment = '';
            for(var i = 0; i < r.length; i++){
                replacment += '1';
            }

            regexp = new RegExp('(\\[%'+ r.name +'.*?%\\])');
            _content = _content.replace(regexp, replacment);
        }

        _content = _content.replace(/(\[%.*?%\])/, '12345');

		switch(unicode) {
			case 1:
					return _content.length;
				break;
			default:

				_content = _content.replace(/([\^\[\]{}~|\\€])/g, ' $1');

				return _content.length;

				break;
		}
	}
}

sms_counter = function ( $elements, root ) {

  $( $elements ).live( 'change', function() {

    if(!this.sms_counter) {

      this.sms_counter = new sms_counter_new($(this));
      var sms_counter = this.sms_counter;

      if( typeof( root ) === 'string' ) {
        $root = $(root);

      } else if( ! root  ) {
        $root = $(this).parents('form:first');
      } else {
        $root = root;
      }

      sms_counter.$left = $root.find('[data-sms-counter="left"]');
      sms_counter.$count = $root.find('[data-sms-counter="count"]');
      sms_counter.$flash = $root.find('input[name=flash]');
      sms_counter.$gsm = $root.find('input[name=gsm]');
      sms_counter.$normalize = $root.find('input[name=normalize]');
      sms_counter.$special_hint = $root.find('[data-sms-counter="sms_not_gsm"]');
    }

    this.sms_counter.recount( $(this).val() );

  }).live( 'keyup', function() {
    $elem = $(this)
    setTimeout(function() {
      textarea_changed( $elem )
    }, 50);
  }).trigger('change');

}


});
