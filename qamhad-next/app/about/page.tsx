import type { Metadata } from 'next';
import { SITE_NAME } from '@/lib/site';

export const metadata: Metadata = {
  title: 'من نحن',
  description: `${SITE_NAME} — منصة رياضية عربية متكاملة لعشاق كرة القدم: نتائج مباشرة، مركز مباريات، ترتيب، هدافون وأخبار موثوقة.`,
  alternates: { canonical: '/about' },
};

export default function AboutPage() {
  return (
    <>
      <div className="container page-head"><h1>من نحن</h1></div>
      <div className="container legal-page">
        <div className="legal-card">
          <p className="legal-intro"><strong>{SITE_NAME}</strong> منصة رياضية عربية متكاملة لعشاق كرة القدم: نتائج مباشرة لحظة بلحظة، مركز مباريات تفصيلي، جداول ترتيب، هدافون، وأخبار رياضية موثوقة — بتجربة سريعة وعصرية باللغتين العربية والإنجليزية.</p>
          <div className="legal-body">
            <h2>رسالتنا</h2>
            <p>أن نكون الوجهة الأولى للجمهور العربي لمتابعة كرة القدم، عبر تقديم معلومة دقيقة وسريعة في واجهة نظيفة تحترم وقت الزائر وتعمل بكفاءة على أي جهاز وأي سرعة اتصال.</p>

            <h2>ماذا نقدم</h2>
            <div className="about-grid">
              <div className="about-tile">
                <svg viewBox="0 0 24 24" width={22} height={22} fill="none" stroke="currentColor" strokeWidth={2}><circle cx={12} cy={12} r={9} /><path d="M12 7v5l3 3" /></svg>
                <b>نتائج مباشرة</b>
                <p>تحديث لحظي للأهداف والبطاقات وأحداث المباريات في أهم البطولات العالمية والعربية.</p>
              </div>
              <div className="about-tile">
                <svg viewBox="0 0 24 24" width={22} height={22} fill="none" stroke="currentColor" strokeWidth={2}><path d="M5 20V10M12 20V4M19 20v-7" /></svg>
                <b>إحصاءات وجداول</b>
                <p>جداول الترتيب، قوائم الهدافين، تشكيلات الفرق، وإحصاءات تفصيلية لكل مباراة ولاعب.</p>
              </div>
              <div className="about-tile">
                <svg viewBox="0 0 24 24" width={22} height={22} fill="none" stroke="currentColor" strokeWidth={2}><rect x={3} y={4} width={18} height={16} rx={3} /><path d="M7 9h10M7 13h6" /></svg>
                <b>أخبار موثوقة</b>
                <p>تغطية إخبارية لأهم الانتقالات والمستجدات من مصادر رياضية معتمدة.</p>
              </div>
              <div className="about-tile">
                <svg viewBox="0 0 24 24" width={22} height={22} fill="none" stroke="currentColor" strokeWidth={2}><rect x={5} y={2} width={14} height={20} rx={3} /><path d="M12 18h.01" /></svg>
                <b>تطبيق ويب حديث</b>
                <p>ثبّت الموقع كتطبيق على هاتفك، وفعّل الإشعارات الفورية للأهداف وبداية المباريات ونهايتها.</p>
              </div>
            </div>

            <h2>تغطيتنا</h2>
            <p>نغطي أبرز البطولات: كأس العالم، دوري أبطال أوروبا، الدوريات الأوروبية الخمسة الكبرى، دوري روشن السعودي، الدوري المصري، البطولات الخليجية والعربية، وكأس أمم أفريقيا وآسيا — مع صفحات مخصصة لكل بطولة وفريق ولاعب.</p>

            <h2>قيمنا التحريرية</h2>
            <ul>
              <li><strong>الدقة قبل السبق:</strong> نعتمد على مزودي بيانات رياضية موثوقين ونصحح أي خطأ فور اكتشافه.</li>
              <li><strong>الحياد:</strong> لا ننحاز لفريق أو اتحاد، ونعرض الأرقام كما هي.</li>
              <li><strong>احترام الزائر:</strong> صفحات سريعة، تصميم واضح، وبدون ممارسات مزعجة.</li>
            </ul>

            <h2>تواصل معنا</h2>
            <p>نرحب دائماً بملاحظاتكم واقتراحاتكم عبر صفحة <a href="/contact">اتصل بنا</a>، أو مباشرة على <a href="mailto:info@qamhad.com">info@qamhad.com</a>.</p>
          </div>
        </div>
      </div>
    </>
  );
}
