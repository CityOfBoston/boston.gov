/**
 * @file
 * Script to run Google translate service.
 */

'use strict';

jQuery(document).ready( function () {

  translate.init();
  covidPages.init();

});

// Add notranslate class to multilingual Covid 19 pages
let covidPages = function() {
  return {
    init:function() {
      this.multilingual();
    }
    , multilingual:function() {
      let trArray = [11564731, 11564741, 11564716, 11564756, 11564736, 11564746, 11564721, 11564711, 11564706];
      let $this = jQuery('#page');
      if (jQuery.inArray($this.data('target'), trArray) !== -1) {
        $this.find('#content').addClass('notranslate');
        $this.find('#targetLanguage').css('display','none');
      }
    }
  }
}();

let translate = function(d){
  return {
    init:function() {
      this.writeLanguageList();
      this.bindDomEvents()
    }
    , exec:function() {
      this.writeLanguageList();
      this.bindDomEvents()
    }
    , bindDomEvents:function() {
      let e=this;
      jQuery("#cob_translate, #langCloseButton").on("click", function(f) {
          f.preventDefault();
          e.toggleLanguages();
          jQuery('#overlay-background').addClass('md');
        }
      );
      jQuery("#viewDisclaimer, #closeDisclaimer").on("click", function(f) {
          f.preventDefault();
          e.showDisclaimer()
        }
      );
      jQuery("#disclaimerLanguageSelect").on("change", function(f) {
          e.selectDisclaimerLanguage()
        }
      );
      jQuery(".translateLink").on("click", function(g) {
          let f=jQuery(this);
          e.trackEvent(document.location.href, function() {
              document.location=f.attr("href")
            }
          )
        }
      )
    }
    , writeDisclaimer:function() {
      let g="";
      g+='<form id="translateForm" class="translate-form"><label for="disclaimerLanguageSelect">View Disclaimer in: </label><select id="disclaimerLanguageSelect" name="disclaimerLanguageSelect" class="translate-select-disclaimer">';
      g+='<option value="English">English</option>';
      g+='<option value="Spanish">Spanish</option>';
      g+='<option value="Haitian">Haitian Creole</option>';
      g+='<option value="Portuguese">Portuguese</option>';
      g+='<option value="French">French</option>';
      g+='<option value="ChineseS">Chinese - Simplified</option>';
      g+='<option value="Vietnamese">Vietnamese</option>';
      g+='<option value="Russian">Russian</option>';
      g+='<option value="Somali">Somali</option>';
      g+='<option value="Arabic">Arabic</option></select>';
      let h='<div id="English" class="translate-disclaimer disclaimer"><h4>About Translations on Boston.gov</h4>';
      h+="<p>The City of Boston Department of Innovation and Technology (“DoIT”) offers translations of the content on Boston.gov through the Google Translate web translator (translate.google.com). Because Google Translate is an external website, DoIT does not control the quality or accuracy of translated content. This may result in inaccurate translated text, or other errors in images and the general appearance of translated pages.</p>";
      h+="<p>However, you can report incorrect or substandard translations and contribute better translations using Google Translate.</p>";
      h+="<ol><li>First, hover over and click on any text containing an error. A pop up box should appear.</li>";
      h+="<li>Next, click “Contribute a better translation”.</li>";
      h+="<li>Double click the area of the pop up that reads “Click a word for alternative translations, or double-click to edit directly.”</li>";
      h+="<li>Make your edits directly to the text in the text box.</li>";
      h+="<li>Finally, press Contribute to contribute your suggested edits.</li></ol>";
      h+="<p>More information about contributing to Google Translate can be found <a href='https://support.google.com/translate/answer/2534530?hl=en&ref_topic=7010955'>here.</a></p>";
      h+="<p>Please note that DoIT does not control the process by which contributed translations are incorporated into the Google web translator.</p>";
      h+="<p class='translate-d-c'>The City of Boston is committed to improving the quality and breadth of multilingual content on our website.</p></div>";
      let j='<div id="Spanish" class="translate-disclaimer disclaimer" style="display:none;"><h4>Acerca de las traducciones en Boston.gov</h4>';
      j+="<p>El Departamento de Innovación y Tecnología de la Ciudad de Boston (”DoIT”) ofrece traducciones del contenido en Boston.gov a través del traductor web Google Translate (translate.google.com). Porque Google Translate es un sitio web externo , DoIT no controla la calidad ni la precisión del contenido traducido. Esto puede resultar en texto traducido incorrecto u otros errores en las imágenes y la apariencia general de las páginas traducidas.</p>";
      j+="<p>Sin embargo, puede informar traducciones incorrectas o de calidad inferior y contribuir con mejores traducciones usando Google Translate.</p>";
      j+="<ol><li>Primero, desplace el mouse y haga clic en cualquier texto que contenga un error. Debería aparecer un cuadro emergente.</li>";
      j+="<li>A continuación, haga clic en ”Contribuir a una mejor traducción”.</li>";
      j+="<li>Haga doble clic en el área de la ventana emergente que dic ”Haga clic en una palabra para traducciones alternativas, o haga doble clic para editar directamente.”</li>";
      j+="<li>Realice sus ediciones directamente al texto en el cuadro de texto.</li>";
      j+="<li>Finalmente, presione Contribuir para contribuir con las ediciones sugeridas.</li></ol>";
      j+="<p>Puede encontrar más información sobre cómo contribuir al Traductor de Google <a href='https://support.google.com/translate/answer/2534530?hl=en&ref_topic=7010955'> aquí.</a></p>";
      j+="<p>Tenga en cuenta que DoIT no controla el proceso mediante el cual las traducciones contribuidas se incorporan al traductor web de Google.</p>";
      j+="<p class='translate-d-c'>La Ciudad de Boston se compromete a mejorar la calidad y la amplitud del contenido multilingüe en nuestro sitio web.</p></div> ";
      let e='<div id="ChineseS" class="translate-disclaimer disclaimer" style="display:none;"><h4>关于Boston.gov上的翻译</h4>';
      e+="<p>波士顿市创新技术局（“DoIT”）通过Google翻译网络翻译器（translate.google.com）在Boston.gov上提供内容的翻译。 由于Google Translate是外部网站，因此DoIT不能控制翻译内容的质量或准确性。 这可能会导致翻译文本不正确，或图像中的其他错误以及翻译页面的整体外观。</p>";
      e+="<p>但是，您可以报告不正确或不合格的翻译，并使用Google翻译来提供更好的翻译。</p>";
      e+="<ol><li>首先，将鼠标悬停并单击任何包含错误的文本。应出现一个弹出框。</li>";
      e+="<li>下一步，单击“贡献更好的翻译”。</li>";
      e+="<li>双击弹出窗口中的区域，即“单击单词以进行其他翻译，或双击以直接编辑。”</li>";
      e+="<li>直接对文本框中的文本进行编辑。</li>";
      e+="<li>最后，按贡献来贡献您的建议编辑。</li></ol>";
      e+="<p>有关对Google翻译做出贡献的更多信息，可在此处<a href='https://support.google.com/translate/answer/2534530?hl=zh_CN&ref_topic=7010955'>找到。</a></p>";
      e+="<p>请注意，DoIT不能控制将贡献的翻译内容整合到Google网络翻译器中的过程。</p>";
      e+="<p class='translate-d-c'>波士顿市致力于提高我们网站上多语种内容的质量和广度。</p></div>";
      let f='<div id="Haitian" class="translate-disclaimer disclaimer" style="display:none;"><h4>Sou tradiksyon sou Boston.gov</h4>';
      f+="<p>Depatman Inovasyon ak Teknoloji Vil Boston (”DoIT”) ofri tradiksyon kontni sou Boston.gov nan tradiktè entènèt Google Translate (translate.google.com). Paske Google Translate se yon sit entènèt ekstèn , DoIT pa kontwole bon jan kalite a oswa presizyon nan kontni tradui. Sa a pouvwa rezilta nan kòrèk tèks tradui, oswa lòt erè nan imaj ak aparans la an jeneral nan paj tradui.</p>";
      f+="<p>Sepandan, ou ka rapòte tradiksyon ki pa kòrèk oswa medyòm epi kontribye pi byen tradiksyon lè l sèvi avèk Google Translate.</p>";
      f+="<ol><li>Premyèman, monte sou epi klike sou nenpòt ki tèks ki gen yon erè. Yon bwat pòp moute ta dwe parèt.</li>";
      f+="<li>Apre sa, klike sou ”Kontribye yon pi bon tradiksyon”.</li>";
      f+="<li>Double klike sou zòn nan nan pòp moute a ki li ”Klike sou yon mo pou tradiksyon altènatif, oswa doub-klike sou edite dirèkteman.”</li>";
      f+="<li>Fè edits ou dirèkteman nan tèks la nan bwat tèks la.</li>";
      f+="<li>Finalman, peze Kontribye pou kontribiye edisyon sijere ou.</li></ol>";
      f+="<p>Plis enfòmasyon sou kontribiye nan Google Translate ka jwenn <a href='https://support.google.com/translate/answer/2534530?hl=en&ref_topic=7010955'>isit la.</a></p>";
      f+="<p>Tanpri sonje ke DoIT pa kontwole pwosesis la pa ki kontribye tradiksyon yo enkòpore nan Google tradiktè wèb la.</p>";
      f+="<p class='translate-d-c'>Vil Boston pran angajman pou amelyore kalite ak lajè kontni pale plizyè lang sou sit entènèt nou an. </p></div>";
      let m='<div id="Portuguese" class="translate-disclaimer disclaimer" style="display:none;"><h4>Sobre as traduções no Boston.gov</h4>';
      m+="<p>O Departamento de Inovação e Tecnologia da cidade de Boston (“ DoIT ”) oferece traduções do conteúdo em Boston.gov por meio do tradutor da web do Google Translate (translate.google.com). Como o Google Translate é um site externo , O DoIT não controla a qualidade ou a precisão do conteúdo traduzido. Isso pode resultar em texto traduzido impreciso ou em outros erros nas imagens e na aparência geral das páginas traduzidas.</p>";
      m+="<p>No entanto, você pode denunciar traduções incorretas ou abaixo do padrão e contribuir com traduções melhores usando o Google Tradutor.</p>";
      m+="<ol>";
      m+="<li>Primeiro, passe o mouse e clique em qualquer texto que contenha um erro. Uma caixa pop-up deve aparecer.</li>";
      m+="<li>Em seguida, clique em ”Contribua para uma tradução melhor”.</li>";
      m+="<li>Clique duas vezes na área do pop-up que diz ”Clique em uma palavra para obter traduções alternativas ou clique duas vezes para editar diretamente.”</li>";
      m+="<li>Faça suas edições diretamente no texto na caixa de texto.</li>";
      m+="<li>Por fim, pressione Contribute para contribuir com as edições sugeridas.</li>";
      m+="</ol>";
      m+="<p>Mais informações sobre como contribuir para o Google Tradutor podem ser encontradas <a href='https://support.google.com/translate/answer/2534530?hl=en&ref_topic=7010955'>aqui.</a></p>";
      m+="<p>Observe que o DoIT não controla o processo pelo qual as traduções contribuídas são incorporadas ao tradutor da web do Google.</p>";
      m+="<p class='translate-d-c'>A cidade de Boston está comprometida em melhorar a qualidade e a abrangência do conteúdo multilíngue em nosso site.</p></div>";
      let n='<div id="French" class="translate-disclaimer disclaimer" style="display:none;"><h4>À propos des traductions sur Boston.gov</h4>';
      n+="<p>Le Département de l'innovation et de la technologie de la ville de Boston (“DoIT“) propose des traductions du contenu de Boston.gov via le traducteur Web Google Translate (translate.google.com). Parce que Google Translate est un site Web externe , DoIT ne contrôle pas la qualité ou l'exactitude du contenu traduit. Cela peut entraîner un texte traduit inexact ou d'autres erreurs dans les images et l'apparence générale des pages traduites.</p>";
      n+="<p>Cependant, vous pouvez signaler des traductions incorrectes ou de qualité inférieure et contribuer à de meilleures traductions à l'aide de Google Translate.</p>";
      n+="<ol><li>Tout d'abord, survolez et cliquez sur tout texte contenant une erreur. Une fenêtre contextuelle devrait apparaître.</li>";
      n+="<li>Ensuite, cliquez sur “Contribuer à une meilleure traduction“.</li>";
      n+="<li>Double-cliquez sur la zone du pop-up qui se lit “Cliquez sur un mot pour des traductions alternatives, ou double-cliquez pour modifier directement.“</li>";
      n+="<li>Apportez vos modifications directement au texte dans la zone de texte.</li>";
      n+="<li>Enfin, appuyez sur Contribuer pour apporter vos modifications suggérées.</li></ol>";
      n+="<p>Vous trouverez plus d'informations sur la contribution à Google Translate <a href='https://support.google.com/translate/answer/2534530?hl=en&ref_topic=7010955'>ici.</a></p>";
      n+="<p>Veuillez noter que DoIT ne contrôle pas le processus par lequel les traductions apportées sont incorporées dans le traducteur Web Google.</p>";
      n+="<p class='translate-d-c'>La ville de Boston s'est engagée à améliorer la qualité et l'étendue du contenu multilingue sur notre site Web.</p></div>";
      let o='<div id="Vietnamese" class="translate-disclaimer disclaimer" style="display:none;"><h4>Về bản dịch trên Boston.gov</h4>';
      o+="<p>Sở Sáng tạo và Công nghệ Thành phố Boston (Tiếng DoIT xông) cung cấp các bản dịch nội dung trên Boston.gov thông qua trình dịch web của Google Dịch (translate.google.com). Bởi vì Google Dịch là một trang web bên ngoài , DoIT không kiểm soát chất lượng hoặc độ chính xác của nội dung dịch. Điều này có thể dẫn đến văn bản dịch không chính xác hoặc các lỗi khác trong hình ảnh và sự xuất hiện chung của các trang được dịch.</p>";
      o+="<p>Tuy nhiên, bạn có thể báo cáo bản dịch không chính xác hoặc không đạt tiêu chuẩn và đóng góp bản dịch tốt hơn bằng Google Dịch.</p>";
      o+="<ol><li>Đầu tiên, di chuột qua và nhấp vào bất kỳ văn bản nào có lỗi. Một hộp bật lên sẽ xuất hiện.</li>";
      o+="<li>Tiếp theo, nhấp vào Đóng góp bản dịch tốt hơn.</li>";
      o+="<li>Nhấp đúp vào khu vực bật lên để đọc Số lần nhấp vào một từ để dịch thay thế hoặc nhấp đúp để chỉnh sửa trực tiếp. LIÊN</li>";
      o+="<li>Thực hiện các chỉnh sửa của bạn trực tiếp cho văn bản trong hộp văn bản.</li>";
      o+="<li>Cuối cùng, nhấn Đóng góp để đóng góp các chỉnh sửa được đề xuất của bạn.</li></ol>";
      o+="<p>Thông tin thêm về việc đóng góp cho Google Dịch có thể được tìm thấy <a href='https://support.google.com/translate/answer/2534530?hl=en&ref_topic=7010955'>tại đây.</a></p>";
      o+="<p>Xin lưu ý rằng DoIT không kiểm soát quá trình dịch thuật đóng góp được tích hợp vào trình dịch web của Google.</p>";
      o+="<p class='translate-d-c'>hành phố Boston cam kết cải thiện chất lượng và độ rộng của nội dung đa ngôn ngữ trên trang web của chúng tôi. </p></div>";
      let p='<div id="Russian" class="translate-disclaimer disclaimer" style="display:none;"><h4>О переводах на Boston.gov</h4>';
      p+="<p>Департамент инноваций и технологий города Бостона (“DoIT“) предлагает переводы контента на Boston.gov через веб-переводчик Google Translate (translate.google.com). Поскольку Google Translate является внешним веб-сайтом , DoIT не контролирует качество или точность переведенного контента. Это может привести к неточному переведенному тексту или другим ошибкам в изображениях и общему виду переведенных страниц.</p>";
      p+="<p>Однако вы можете сообщать о неправильных или некачественных переводах и вносить более качественные переводы с помощью Google Translate.</p>";
      p+="<ol><li>Сначала наведите курсор мыши и щелкните любой текст, содержащий ошибку. Должно появиться всплывающее окно.</li>";
      p+="<li>Далее нажмите “Внести лучший перевод“.</li>";
      p+="<li>Дважды щелкните область всплывающего окна с надписью “Щелкните слово для альтернативных переводов или дважды щелкните, чтобы отредактировать напрямую.“</li>";
      p+="<li>Внесите изменения непосредственно в текст в текстовом поле.</li>";
      p+="<li>Наконец, нажмите Contribute для внесения предложенных вами изменений.</li></ol>";
      p+="<p>Дополнительную информацию о содействии переводчику Google можно найти <a href='https://support.google.com/translate/answer/2534530?hl=en&ref_topic=7010955'>здесь.</a></p>";
      p+="<p>Обратите внимание, что DoIT не контролирует процесс, с помощью которого переводы, включенные в перевод, включаются в веб-переводчик Google.</p>";
      p+="<p class='translate-d-c'>Город Бостон стремится улучшить качество и широту многоязычного контента на нашем веб-сайте. </p></div>";
      let q='<div id="Somali" class="translate-disclaimer disclaimer" style="display:none;"><h4>Ku Saabsan Tarjumida bogga Boston.gov</h4>';
      q+="<p>Magaalada Boston Waaxda Cusbooneysiinta iyo Tiknolojiyadda (“DoIT“) waxay bixisaa tarjumaadda waxa kujira Boston.gov iyada oo loo marinayo turjubaanka websaydhka ee Google Translate (translate.google.com). , DoIT ma xukumaan tayada ama sax ahaanta waxyaabaha la tarjumay. Tani waxay ku dambayn kartaa qoraal aan sax ahayn oo la tarjumay, ama khaladaad kale oo ku saabsan sawirrada iyo muuqaalka guud ee bogagga la turjumay.</p>";
      q+="<p>Si kastaba ha noqotee, waad soo sheegi kartaa tarjumaad qaldan ama kuwa hooseeya waxaadna gacan ka geysan kartaa tarjumaad wanaagsan adiga oo adeegsanaya Google Translate.</p>";
      q+="<ol><li>Marka hore, dul mari oo riix qoraal kasta oo qalad ku jiro. Sanduuqa kor u kaca waa inuu muuqdaa.</li>";
      q+="<li>Marka xigta, dhagsii “Ku tabaruc turjumaad wanaagsan“. </li>";
      q+="<li>laba jeer guji aagga pop-ka ee akhrinaya “Guji eray u dhiganta tarjumaadaha kale, ama laba-guji si aad toos u tafatirto.“</li>";
      q+="<li>Si toos ah ugu samee tafatirayaashaada qoraalka sanduuqa qoraalka.</li>";
      q+="<li>Ugu dambayntii, riix tabaruc si aad ugu biiriso tifatirkaaga la soo jeediyay.</li></ol>";
      q+="<p>Macluumaad dheeri ah oo ku saabsan ku biirinta Google Translate waxaa laga heli karaa <a href='https://support.google.com/translate/answer/2534530?hl=en&ref_topic=7010955'>halkan.</a></p>";
      q+="<p>Fadlan la soco in DoIT ma xakameyso howsha tarjumaadaha tabaruca ah lagu daray tarjume webka Google.</p>";
      q+="<p class='translate-d-c'>agaalada Boston waxa ka go'an inay hagaajiso tayada iyo ballaadhka waxyaabaha ku qoran luqadaha badan luqadaha kala duwan. </p></div>";
      let r='<div id="Arabic" class="translate-disclaimer disclaimer" style="display:none; direction: rtl;"><h4>Boston.gov حول الترجمات على</h4>';
      r+="<p>تقدم دائرة الابتكار والتكنولوجيا في مدينة بوسطن (“DoIT“) ترجمة للمحتوى على Boston.gov من خلال مترجم الويب الخاص بترجمة Google (translate.google.com). نظرًا لأن الترجمة من Google هي موقع ويب خارجي ، فإن DoIT لا تتحكم في جودة أو دقة المحتوى المترجم. قد يؤدي هذا إلى نص مترجم غير دقيق ، أو أخطاء أخرى في الصور والمظهر العام للصفحات المترجمة.</p>";
      r+="<p>ومع ذلك ، يمكنك الإبلاغ عن ترجمات غير صحيحة أو دون المستوى المطلوب والمساهمة في ترجمات أفضل باستخدام الترجمة من Google.</p>";
      r+="<ol><li>أولاً ، مرر الماوس فوق أي نص يحتوي على خطأ وانقر عليه. يجب أن يظهر مربع منبثق.</li>";
      r+="<li>بعد ذلك ، انقر فوق “المساهمة بترجمة أفضل“.</li>";
      r+="<li>انقر نقرًا مزدوجًا فوق منطقة النافذة المنبثقة التي تقول “انقر فوق كلمة للحصول على ترجمات بديلة ، أو انقر نقرًا مزدوجًا للتعديل مباشرة“.</li>";
      r+="<li>قم بإجراء تعديلاتك مباشرة على النص الموجود في مربع النص.</li>";
      r+="<li>أخيرًا ، اضغط على مساهمة للمساهمة بتعديلاتك المقترحة.</li></ol>";
      r+="<p>يمكن العثور على مزيد من المعلومات حول المساهمة في ترجمة Google <a href='https://support.google.com/translate/answer/2534530?hl=en&ref_topic=7010955'>هنا.</a></p>";
      r+="<p>يرجى ملاحظة أن DoIT لا تتحكم في العملية التي يتم من خلالها دمج الترجمات المساهمة في مترجم الويب من Google.</p>";
      r+="<p class='translate-d-c'>زم مدينة بوسطن بتحسين جودة واتساع المحتوى متعدد اللغات على موقعنا. </p></div>";
      g+=h;
      g+=j;
      g+=e;
      g+=f;
      g+=m;
      g+=n;
      g+=o;
      g+=p;
      g+=q;
      g+=r;
      g+="</form>";
      return g
    }
    , writeLanguageList:function() {
      let l=window.location.toString();
      if(l.slice(-1)=="#") {
        let k=l.substring(1, (l.length-1));
        l=k
      }
      let h="//translate.google.com/translate?hl=en&sl=en&tl=";
      let j="";
      let i;
      j+='<div id="languages" class="translate-languages notranslate"><a class="md-cb" href="#" id="langCloseButton"></a>';
      let g=[["af", "Afrikaans"], ["sq", "shqip"], ["am", "አማርኛ"], ["ar", "العربية"], ["hy", "հայերեն"], ["az", "آذربایجان دیل"], ["eu", "Euskara"], ["be", "Беларуская мова"], ["bn", "বাংলা"], ["bs", "بۉسانسقى"], ["bg", "български"], ["ca", "català"], ["ceb", "Binisaya"], ["ny", "Chicheŵa"], ["zh-CN", "广东话"], ["zh-TW", "廣東話"], ["co", "Corsu"], ["hr", "Hrvatski"], ["cs", "čeština"], ["da", "dansk"], ["nl", "Nederlands"], ["eo", "Esperanto"], ["et", "eesti keel"], ["tl", "Pilipino"], ["fi", "suomi"], ["fr", "français"], ["fy", "Ōstfräisk"], ["gl", "galego"], ["ka", "ქართული ენა"], ["de", "Deutsch"], ["el", "Ελληνικά"], ["gu", "ગુજરાતી"], ["ht", "Kreyòl ayisyen"], ["ha", "هَرْشَن هَوْسَ"], ["haw", "ʻŌlelo Hawaiʻi"], ["iw", "עִברִית"], ["hi", "हिंदी"], ["hmn", "Lus Hmoob"], ["hu", "Magyar"], ["is", "íslenska"], ["ig", "Ásụ̀sụ̀ Ìgbò"], ["id", "bahasa Indonesia"], ["ga", "Gaeilge"], ["it", "Italiano"], ["ja", "日本語"], ["jw", "باسا جاوا"], ["kn", "ಕನ್ನಡ"], ["kk", "Қазақ тілі"], ["km", "ភាសាខ្មែរ"], ["ko", "한국인"], ["ku", "کورمانجی"], ["ky", "Кыргыз тили"], ["lo", "ລາວ"], ["la", "Lingua Latina"], ["lv", "latviešu valoda"], ["lt", "lietuvių kalba"], ["lb", "Lëtzebuergesch"], ["mk", "македонски"], ["mg", "malagasy"], ["ms", "بهاس ملايو"], ["ml", "മലയാളം"], ["mt", "Malti"], ["mi", "Māori"], ["mr", "मराठी"], ["mn", "монгол"], ["my", "မြန်မာစကား"], ["ne", "नेपाली"], ["no", "norsk"], ["ps", "پښتو"], ["fa", "فارسی"], ["pl", "Polskie"], ["pt", "Português"], ["pa", "ਪੰਜਾਬੀ"], ["ro", "limba română"], ["ru", "Русский"], ["sm", "Gagana fa‘a Sāmoa"], ["gd", "Gàidhlig"], ["sr", "Српски"], ["st", "Sotho"], ["sn", "chiShona"], ["sd", "سنڌي"], ["si", "සිංහල"], ["sk", "slovenčina"], ["sl", "slovenščina"], ["so", "Soomaali"], ["es", "Español"], ["su", "Basa Sunda"], ["sw", "کِسْوَهِيلِ"], ["sv", "svenska"], ["tg", "тоҷикӣ"], ["ta", "தமிழ்"], ["te", "తెలుగు"], ["th", "ไทย"], ["'tr'", "Türkçe"], ["uk", "українська мова"], ["ur", "اردو"], ["uz", "أۇزبېك ﺗﻴﻠی"], ["vi", "Tiếng Việt"], ["cy", "Cymraeg"], ["xh", "isiXhosa"], ["yi", "יידיש"], ["yo", "Èdè Yorùbá"], ["zu", "isiZulu"]];
      j+='<div style="float:left;"><ul class="translate-dd" style="list-style:none;">';
      j+='			<li><a href="#" id="viewDisclaimer" class="translate-dd-link">View Disclaimer</a></li>';
      j+='			<li><a href="'+h+'es&u='+l+'" class="translateLink translate-dd-link">Español</a></li>';
      j+='			<li><a href="'+h+'ht&u='+l+'" class="translateLink translate-dd-link">Kreyòl ayisyen</a></li>';
      j+='			<li><a href="'+h+'pt&u='+l+'" class="translateLink translate-dd-link">Português</a></li>';
      j+='			<li><a href="'+h+'fr&u='+l+'" class="translateLink translate-dd-link">français</a></li>';
      j+='			<li><a href="'+h+'zh-CN&u='+l+'" class="translateLink translate-dd-link">简体中文</a></li>';
      j+='			<li><a href="'+h+'vi&u='+l+'" class="translateLink translate-dd-link">Tiếng Việt</a></li>';
      j+='			<li><a href="'+h+'ru&u='+l+'" class="translateLink translate-dd-link">Русский</a></li>';
      j+='			<li><a href="'+h+'so&u='+l+'" class="translateLink translate-dd-link">Soomaali</a></li>';
      j+='			<li><a href="'+h+'ar&u='+l+'" class="translateLink translate-dd-link ar">العربية</a></li>';
      j+="<hr>";
      for(i=0;
        i<30;
        i++) {
        j+='<li><a href="'+h+g[i][0]+'&u='+l+'" class="translateLink translate-dd-link '+g[i][0]+'">'+g[i][1]+"</a></li>"
      }
      j+=" 		</ul></div>";
      j+='<div  style="float:left;"><ul class="translate-dd" style="list-style:none;">';
      for(i=30;
        i<66;
        i++) {
        j+='<li><a href="'+h+g[i][0]+'&u='+l+'" class="translateLink translate-dd-link '+g[i][0]+'">'+g[i][1]+"</a></li>"
      }
      j+=" 		</ul></div>";
      j+='<div  style="float:left;"><ul class="translate-dd" style="list-style:none;">';
      for(i=66;
        i<g.length;
        i++) {
        j+='<li><a href="'+h+g[i][0]+'&u='+l+'" class="translateLink translate-dd-link '+g[i][0]+' ">'+g[i][1]+"</a></li>"
      }
      j+=" 		</ul></div>";
      j+="</div>";
      j+='<div id="translateMessage" class="translate-message" data-nosnippet><a href="#" id="closeDisclaimer" class="md-cb"></a>'+this.writeDisclaimer()+"</div>";
      let e=jQuery("#overlay");
      e.html(j)
    }
    , toggleLanguages:function() {
      let f=document.getElementById("overlay");
      f.style.visibility=(f.style.visibility=="visible")?"hidden": "visible";
      let g=document.getElementById("overlay-background");
      g.style.visibility=(g.style.visibility=="visible")?"hidden": "visible";
      let e=document.getElementById("languages");
      e.style.display=(e.style.display=="block")?"none": "block"
    }
    , showDisclaimer:function() {
      let f=document.getElementById("translateMessage");
      f.style.display=(f.style.display=="block")?"none": "block";
      let e=document.getElementById("languages");
      e.style.display=(e.style.display=="block")?"none": "block"
    }
    , selectDisclaimerLanguage:function() {
      let j=document.getElementById("disclaimerLanguageSelect");
      let g=document.getElementById("translateMessage");
      if(g!=null) {
        let h=g.getElementsByClassName("disclaimer");
        for(let e=0;
          e<h.length;
          e++) {
          let f=h[e];
          if(f.id==j.options[j.selectedIndex].value) {
            f.style.display="inherit"
          }
          else {
            f.style.display="none"
          }
        }
      }
    }
    , selectTranslateLanguage:function(e) {
      let g=document.getElementById("translateForm");
      if(g!=null&&e!=null&&e.value!="") {
        g.tl.value=e.value;
        g.submit()
      }
    }
  }
}
();
