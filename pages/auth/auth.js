const api = require('../../utils/api.js');
Page({
  data: { phone: '', smsCode: '', smsBtnText: '发送验证码', smsSending: false },
  onPhoneInput(e) { this.setData({ phone: e.detail.value }) },
  onCodeInput(e) { this.setData({ smsCode: e.detail.value }) },
  onWechatLogin() {
    wx.login({
      success: async (res) => {
        if (res.code) {
          try {
            await api.loginWithWechat(res.code);
            api.reportEvent('auth_request', {});
            wx.redirectTo({ url: '/pages/landing/landing' });
          } catch (e) {
            wx.showToast({ title: '登录失败', icon: 'none' });
          }
        }
      }
    })
  },
  sendSmsCode() {
    if (this.data.smsSending) return;
    // TODO: 调用后端发送验证码
    this.setData({ smsSending: true, smsBtnText: '已发送(60s)' });
    setTimeout(() => this.setData({ smsSending: false, smsBtnText: '发送验证码' }), 60000);
    api.reportEvent('sms_sent', { phone: this.data.phone });
  },
  async onPhoneBind() {
    try {
      await api.bindPhone(null, this.data.phone, this.data.smsCode);
      api.reportEvent('phone_bind_success', {});
      wx.redirectTo({ url: '/pages/landing/landing' });
    } catch (e) {
      wx.showToast({ title: '绑定失败', icon: 'none' });
      api.reportEvent('phone_bind_fail', {});
    }
  }
})
