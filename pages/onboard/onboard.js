const api = require('../../utils/api.js');
Page({
  data: { inviter: '', inviterName: '好友' },
  onLoad(options) {
    if (options.inviter) {
      this.setData({ inviter: options.inviter });
      // 可以请求邀请人信息
    }
    api.reportEvent('landing_from_share', { inviter: options.inviter || null });
  },
  quickRegister() {
    // 演示：若需要微信登录则在此调用 wx.login + 后端绑定
    wx.showToast({ title: '跳转到注册/下单流程（示例）', icon: 'none' });
    api.reportEvent('invitee_register', {});
  }
})
