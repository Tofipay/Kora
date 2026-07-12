import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'اتصل بنا',
  description: 'تواصل مع فريق قمهد لايف: استفسارات عامة، محتوى وحقوق، وإعلانات وشراكات.',
  alternates: { canonical: '/contact' },
};

export default function ContactPage() {
  return (
    <>
      <div className="container page-head"><h1>اتصل بنا</h1></div>
      <div className="container legal-page">
        <div className="legal-card">
          <p className="legal-intro">يسعدنا تواصلك معنا — سواء كان لديك استفسار، اقتراح لتطوير الموقع، ملاحظة على المحتوى، أو رغبة في التعاون الإعلاني. نرد على الرسائل عادةً خلال 24 إلى 48 ساعة عمل.</p>
          <div className="contact-grid">
            <div className="contact-card">
              <span className="contact-ic">
                <svg viewBox="0 0 24 24" width={20} height={20} fill="none" stroke="currentColor" strokeWidth={2}><rect x={3} y={5} width={18} height={14} rx={3} /><path d="m3 7 9 6 9-6" /></svg>
              </span>
              <div>
                <b>الاستفسارات العامة</b>
                <p>لأي سؤال حول الموقع وخدماته.</p>
                <a href="mailto:info@qamhad.com">info@qamhad.com</a>
              </div>
            </div>
            <div className="contact-card">
              <span className="contact-ic">
                <svg viewBox="0 0 24 24" width={20} height={20} fill="none" stroke="currentColor" strokeWidth={2}><path d="M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z" /></svg>
              </span>
              <div>
                <b>المحتوى والحقوق</b>
                <p>لملاحظات المحتوى أو طلبات الحقوق والتصحيح.</p>
                <a href="mailto:info@qamhad.com?subject=Content">info@qamhad.com</a>
              </div>
            </div>
            <div className="contact-card">
              <span className="contact-ic">
                <svg viewBox="0 0 24 24" width={20} height={20} fill="none" stroke="currentColor" strokeWidth={2}><path d="M3 11l18-7-7 18-2.5-7.5z" /></svg>
              </span>
              <div>
                <b>الإعلانات والشراكات</b>
                <p>لعروض التعاون الإعلاني والرعايات.</p>
                <a href="mailto:info@qamhad.com?subject=Ads">info@qamhad.com</a>
              </div>
            </div>
          </div>
          <p className="legal-note">عند مراسلتنا بخصوص مشكلة تقنية، يُفضّل ذكر نوع الجهاز والمتصفح ورابط الصفحة لمساعدتنا على معالجتها بشكل أسرع. للاطلاع على كيفية تعاملنا مع بياناتك راجع <a href="/privacy">سياسة الخصوصية</a>.</p>
        </div>
      </div>
    </>
  );
}
