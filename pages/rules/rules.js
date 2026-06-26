const api = require('../../utils/api.js');
Page({
  onLoad() { api.reportEvent('rules_view', {}); },
  onAppeal() {
    wx.showToast({ title: '申诉已提交（示例）', icon: 'none' });
    api.reportEvent('appeal_submit', {});
  }
})
