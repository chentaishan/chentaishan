const api = require('../../utils/api.js');
Page({
  data: { invitees: [], validCount: 0 },
  async onLoad() {
    try {
      const res = await api.getInvitees();
      this.setData({ invitees: res.list || [], validCount: res.validCount || 0 });
      api.reportEvent('progress_view', {});
    } catch (e) {}
  },
  openInviteDetail(e) {
    const id = e.currentTarget.dataset.id;
    wx.navigateTo({ url: `/pages/progress/detail?id=${id}` });
    api.reportEvent('invite_item_click', { id });
  }
})
