const BASE_URL = "https://api.example.com"; // TODO: 替换为真实后端

function request(path, method = 'GET', data = {}) {
  return new Promise((resolve, reject) => {
    wx.request({
      url: BASE_URL + path,
      method,
      data,
      header: { 'content-type': 'application/json' },
      success(res) {
        if (res.statusCode === 200) resolve(res.data);
        else reject(res);
      },
      fail(err) { reject(err); }
    })
  });
}

module.exports = {
  loginWithWechat: (code) => request('/auth/wechat', 'POST', { code }),
  bindPhone: (openid, phone, code) => request('/auth/bind-phone', 'POST', { openid, phone, code }),
  getUser: () => request('/user/me'),
  getInviteLink: () => request('/user/invite-link'),
  getInvitees: () => request('/user/invitees'),
  getRewards: () => request('/user/rewards'),
  claimReward: (rewardId) => request(`/user/rewards/${rewardId}/claim`, 'POST'),
  reportEvent: (eventName, payload) => request('/telemetry/event', 'POST', { eventName, payload }),
  // poster/qrcode APIs
  getInviteQRCode: (inviteCode) => request(`/user/invite-qrcode?inviteCode=${encodeURIComponent(inviteCode)}`, 'GET'),
  getUserInviteInfo: () => request('/user/invite-link')
}
